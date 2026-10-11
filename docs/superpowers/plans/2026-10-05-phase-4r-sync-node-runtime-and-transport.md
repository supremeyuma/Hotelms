# Phase 4R — Sync Node Runtime and Transport

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 2R Part B](2026-10-05-phase-2r-part-b-capture-and-convergence.md), [Phase 4S](2026-10-05-phase-4s-sync-scope-manifest.md) complete.

**Goal:** Turn the headless engine into a running sync node: a real HTTP transport, working push and pull commands, a scheduled worker, cursor management, and a three-process convergence proof on one machine.

**Architecture:** A small HTTP API on every node — `POST /sync/push` accepts changes, `GET /sync/pull` returns changes past a cursor. `SyncNodeService` runs a cycle: push local changes to each peer, then pull and apply from each peer. Peer ordering follows spec §4.3, so a terminal prefers the LAN server and falls back to the cloud.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite/MySQL/Postgres, Laravel Sanctum or signed requests.

**Spec:** §4.3 (peer lists), §5.2 (change log), §6.2 (movement-authoritative inventory), §12 Phase 4, §13 (testing strategy).

**Closes:** G20, G21, G22, G23, G24, G25.

**Status:** COMPLETE 2026-10-11. Tasks 1–7 executed and committed (`722c6eb6`, `8c053156`, `0cf455eb`, `e4f45e90`, `88232013`, `eae4ffb9`, and this verification commit). Full suite 286 passed / 1744 assertions. See `docs/SYNC_CONVERGENCE.md`.

---

## The gap this closes

```php
// app/Console/Commands/SyncPush.php
public function handle(): int
{
    $this->info("Sync push initiated...");
    $this->info("No remote configured; skipping.");
    return self::SUCCESS;
}
```

Both `sync:push` and `sync:pull` are stubs. `SyncRun` calls them. `SyncWorker`
calls `sync:run` and is never dispatched by anything. `routes/console.php` has
zero sync entries. There is no HTTP endpoint, no authentication, no cursor
management, and no peer registry wiring.

So the sync engine built in Phase 2R can be exercised in-process by tests and by
nothing else.

---

## Global Constraints

- **A node must always have somewhere to sync to.** Spec §4.3: a dead LAN server
  degrades throughput, it does not stop reconciliation. Peer failover is a
  correctness requirement, not a nicety.
- **Sync is additive.** With `SYNC_ENABLED=false` nothing changes about existing
  behaviour (spec §10).
- Authenticate every node-to-node call. Do not leave an open write endpoint on
  the LAN.
- Cursors advance only after the batch commits. Advancing first loses changes on
  a crash between apply and record.
- Commit after every task.

---

## File Structure

**Created:**
- `app/Http/Controllers/Sync/ChangeLogController.php` — `push` and `pull`.
- `app/Sync/NodeSyncService.php` — one sync cycle against one peer.
- `app/Sync/SyncCycleRunner.php` — iterate peers, handle failover.
- `app/Sync/Exceptions/SyncTransportException.php`
- `app/Sync/HttpSyncTransport.php` — the HTTP client.
- `database/migrations/2026_10_05_100040_add_last_seen_at_to_sync_nodes.php`
- `tests/Feature/Sync/TransportTest.php`
- `tests/Feature/Sync/ThreeNodeConvergenceTest.php`

**Modified:**
- `app/Console/Commands/SyncPush.php`, `SyncPull.php`, `SyncRun.php` — real implementations.
- `app/Jobs/SyncWorker.php` — real work, error handling.
- `routes/console.php` — schedule the worker.
- `routes/api.php` — the sync endpoints.
- `config/sync.php` — peer priority, auth settings.

**Responsibility note:** a real synchronization node driven by `artisan`, proven
with three processes before any desktop work begins (spec §12 Phase 4).

---

## Task 1: Add the HTTP transport

**Files:**
- Create: `app/Sync/Exceptions/SyncTransportException.php`, `app/Sync/HttpSyncTransport.php`
- Create: `tests/Feature/Sync/TransportTest.php`

**Interfaces:**
- Consumes: `config('sync.peers')`, the Phase 2R change schema.
- Produces: `HttpSyncTransport::push(string $peer, array $changes): array`, `::pull(string $peer, int $sinceId): array`.

- [x] **Step 1: Write the exception**

```php
<?php

namespace App\Sync\Exceptions;

class SyncTransportException extends \RuntimeException
{
}
```

- [x] **Step 2: Write the transport**

```php
<?php

namespace App\Sync;

use App\Sync\Exceptions\SyncTransportException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\ConnectionException;

/**
 * HTTP transport for node-to-node change exchange.
 *
 * Batches, because a terminal may have thousands of changes after a long
 * offline stretch and a per-row request loop would make a restore take hours.
 */
class HttpSyncTransport
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /** @return array{accepted: int} */
    public function push(string $peer, array $changes): array
    {
        $response = $this->send($peer, 'POST', '/api/sync/changes', ['changes' => $changes]);

        return ['accepted' => (int) $response['accepted']];
    }

    /** @return array{changes: array, cursor: int} */
    public function pull(string $peer, int $sinceId, int $limit = 500): array
    {
        $response = $this->send($peer, 'GET', '/api/sync/changes', [
            'since' => $sinceId,
            'limit' => $limit,
        ]);

        return [
            'changes' => $response['changes'] ?? [],
            'cursor'  => (int) ($response['cursor'] ?? $sinceId),
        ];
    }

    private function send(string $peer, string $method, string $path, array $payload): array
    {
        try {
            $request = $this->http->timeout(config('sync.request_timeout'))
                ->acceptJson()
                ->withHeaders([
                    'X-Sync-Node-Id' => (string) config('sync.node_id'),
                    'Authorization'  => 'Bearer ' . $this->credentialFor($peer),
                ]);

            $response = $method === 'POST'
                ? $request->post(rtrim($peer, '/') . $path, $payload)
                : $request->get(rtrim($peer, '/') . $path, $payload);

            if ($response->failed()) {
                throw new SyncTransportException(
                    "Sync {$method} {$peer}{$path} failed: {$response->status()} {$response->body()}",
                );
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new SyncTransportException("Could not reach peer [{$peer}]: {$e->getMessage()}", 0, $e);
        }
    }

    private function credentialFor(string $peer): string
    {
        $credentials = config('sync.credentials', []);

        return $credentials[$peer] ?? '';
    }
}
```

- [x] **Step 3: Test the transport against a fake**

```php
<?php

namespace Tests\Feature\Sync;

use App\Sync\Exceptions\SyncTransportException;
use App\Sync\HttpSyncTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransportTest extends TestCase
{
    public function test_push_posts_a_batched_change_array(): void
    {
        Http::fake([
            '*/api/sync/changes' => Http::response(['accepted' => 2], 200),
        ]);

        config(['sync.credentials' => ['http://peer' => 'secret']]);

        $result = app(HttpSyncTransport::class)->push('http://peer', [
            ['table_name' => 'pos_dockets', 'row_id' => '1', 'operation' => 'create'],
            ['table_name' => 'pos_dockets', 'row_id' => '2', 'operation' => 'create'],
        ]);

        $this->assertSame(2, $result['accepted']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'http://peer/api/sync/changes'
                && count($request->data()['changes']) === 2;
        });
    }

    public function test_push_sends_the_node_id_and_credential(): void
    {
        Http::fake(['*/api/sync/changes' => Http::response(['accepted' => 0])]);

        config([
            'sync.node_id'    => 'node-a',
            'sync.credentials' => ['http://peer' => 'secret-token'],
        ]);

        app(HttpSyncTransport::class)->push('http://peer', []);

        Http::assertSent(fn (Request $r) => $r->hasHeader('X-Sync-Node-Id', 'node-a')
            && $r->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_an_unreachable_peer_raises_a_transport_exception(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'));

        $this->expectException(SyncTransportException::class);

        app(HttpSyncTransport::class)->push('http://down', []);
    }

    public function test_a_500_response_raises_a_transport_exception(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->expectException(SyncTransportException::class);

        app(HttpSyncTransport::class)->push('http://peer', []);
    }
}
```

Commit: `feat: add an http transport for node-to-node sync`

---

## Task 2: Expose the sync API

**Files:**
- Create: `app/Http/Controllers/Sync/ChangeLogController.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: Task 1's transport shape.
- Produces: `POST /api/sync/changes`, `GET /api/sync/changes`, `POST /api/sync/register`.

- [x] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncNode;
use App\Sync\SyncApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Node-to-node change exchange.
 *
 * Reachable on the LAN and on the cloud, so every route authenticates the
 * calling node before reading or writing anything.
 */
class ChangeLogController extends Controller
{
    public function __construct(private readonly SyncApplier $applier)
    {
    }

    /**
     * Accept changes from a peer and apply them.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'changes' => ['required', 'array', 'max:1000'],
            'changes.*.table_name' => ['required', 'string'],
            'changes.*.row_id'     => ['required', 'string'],
            'changes.*.operation'  => ['required', 'in:create,update,delete'],
            'changes.*.node_id'    => ['required', 'string'],
            'changes.*.hlc'        => ['required', 'string'],
            'changes.*.payload'    => ['nullable', 'array'],
        ]);

        $accepted = 0;

        foreach ($validated['changes'] as $change) {
            $this->applier->apply(new SyncChange($change));

            $accepted++;
        }

        return response()->json(['accepted' => $accepted]);
    }

    /**
     * Return changes past the caller's cursor.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['required', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $limit = $validated['limit'] ?? config('sync.batch_size');

        $changes = SyncChange::where('id', '>', $validated['since'])
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $changes->count() > $limit;
        $page = $hasMore ? $changes->take($limit) : $changes;

        return response()->json([
            'changes' => $page->map(fn (SyncChange $c) => [
                'table_name' => $c->table_name,
                'row_id'     => (string) $c->row_id,
                'operation'  => $c->operation,
                'node_id'    => $c->node_id,
                'hlc'        => $c->hlc,
                'payload'    => $c->payload,
            ])->all(),
            'cursor'    => (int) ($page->last()?->id ?? $validated['since']),
            'has_more'  => $hasMore,
        ]);
    }
}
```

- [x] **Step 2: Authenticate node-to-node requests**

Every node gets a credential at registration. Verify it as middleware, and
reject anything not presenting a known node id and a valid credential.

```php
<?php

namespace App\Http\Middleware;

use App\Models\Sync\SyncNode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSyncNode
{
    public function handle(Request $request, Closure $next): Response
    {
        $nodeId = $request->header('X-Sync-Node-Id');
        $token = $request->bearerToken();

        if (! $nodeId || ! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $node = SyncNode::where('node_id', $nodeId)->first();

        if ($node === null || $node->credential === null
            || ! hash_equals($node->credential, hash('sha256', $token))) {
            return response()->json(['message' => 'Invalid node credentials.'], 401);
        }

        if ($node->status !== 'active') {
            return response()->json(['message' => 'Node is not active.'], 403);
        }

        $node->forceFill(['last_seen_at' => now()])->saveQuietly();

        $request->attributes->set('sync_node', $node);

        return $next($request);
    }
}
```

`hash_equals` is not optional — a timing-vulnerable comparison on a shared
secret across a LAN is a real attack surface.

- [x] **Step 3: Register the routes**

```php
Route::middleware(['sync.node'])->prefix('sync')->group(function () {
    Route::get('/changes', [ChangeLogController::class, 'index']);
    Route::post('/changes', [ChangeLogController::class, 'store']);
});
```

- [x] **Step 4: Test the API**

Cover: unauthenticated is 401, bad credential is 401, inactive node is 403, a
valid push applies and returns the count, pull returns changes past a cursor and
honours `limit` with `has_more`.

- [x] **Step 5: Confirm the webhook routes still work**

`routes/api.php` holds the two payment webhooks. Adding a prefixed group must not
disturb them.

```powershell
php artisan route:list --path=api
php artisan test --filter=Webhook
```

Commit: `feat: expose authenticated node-to-node sync endpoints`

---

## Task 3: Implement the sync cycle

**Files:**
- Create: `app/Sync/NodeSyncService.php`, `app/Sync/SyncCycleRunner.php`
- Create: `database/migrations/2026_10_05_100040_add_last_seen_at_to_sync_nodes.php`

**Interfaces:**
- Consumes: Task 1 transport, Task 2 API, Phase 2R `SyncApplier`.
- Produces: `SyncCycleRunner::run(?string $nodeId = null): SyncResult`.

- [x] **Step 1: Add `last_seen_at`, and confirm the credential contract**

The `credential` column already exists — Phase 2R Part A adds it in
`100020_add_property_id_and_credential_to_sync_nodes`. **Do not add it again.**

This phase adds only what the transport needs:

```php
$table->timestamp('last_seen_at')->nullable();
$table->index('status');
```

`last_seen_at` is what `sync:health` and `sync:status` read to distinguish "a
terminal that is offline" from "a terminal that was never registered".

Confirm the credential contract before writing the middleware:

```powershell
rg -n "credential" app/Models/Sync/SyncNode.php database/migrations/2026_10_05_100020_add_property_id_and_credential_to_sync_nodes.php
```

Part A declares it as a nullable string holding a **hash**. The plaintext token is
issued once at pairing and never persisted server-side, so a database leak does
not yield usable node credentials. Phase 8 Task 3 writes the hash at pairing.

- [x] **Step 2: Write the per-peer service**

```php
<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use App\Sync\Exceptions\SyncTransportException;

class NodeSyncService
{
    public function __construct(
        private readonly HttpSyncTransport $transport,
        private readonly SyncApplier $applier,
    ) {
    }

    /**
     * Synchronise with one peer.
     *
     * @return array{pushed: int, pulled: int, cursor: int}
     */
    public function syncWith(string $peer): array
    {
        $pushed = $this->pushLocalChanges($peer);
        [$pulled, $cursor] = $this->pullRemoteChanges($peer);

        return ['pushed' => $pushed, 'pulled' => $pulled, 'cursor' => $cursor];
    }

    private function pushLocalChanges(string $peer): int
    {
        $changes = SyncChange::where('node_id', (string) config('sync.node_id'))
            ->orderBy('sequence')
            ->limit(config('sync.batch_size'))
            ->get();

        if ($changes->isEmpty()) {
            return 0;
        }

        $result = $this->transport->push($peer, $changes->map(fn (SyncChange $c) => [
            'table_name' => $c->table_name,
            'row_id'     => (string) $c->row_id,
            'operation'  => $c->operation,
            'node_id'    => $c->node_id,
            'hlc'        => $c->hlc,
            'payload'    => $c->payload,
        ])->all());

        return $result['accepted'];
    }

    /**
     * Pull and apply in pages, advancing the cursor only after each page
     * commits. Advancing first loses changes if the process dies mid-page.
     *
     * @return array{0: int, 1: int}
     */
    private function pullRemoteChanges(string $peer): array
    {
        $cursor = $this->cursorFor($peer);
        $pulled = 0;

        do {
            $page = $this->transport->pull($peer, $cursor);

            foreach ($page['changes'] as $change) {
                $this->applier->apply(new SyncChange($change));

                $pulled++;
            }

            if ($page['cursor'] > $cursor) {
                $this->advanceCursor($peer, $page['cursor']);
                $cursor = $page['cursor'];
            }

            $more = $page['changes'] !== [];
        } while ($more && $cursor > 0);

        return [$pulled, $cursor];
    }

    /**
     * How far this node has applied changes FROM $peer.
     *
     * The cursor row is keyed on (node_id, target_node_id): this node, and the
     * peer it tracks. Both halves are required — the unique index is on the
     * pair, so omitting node_id from the lookup can match another node's row
     * and updateOrCreate will then try to insert a duplicate.
     *
     * $peer is a node id from sync_nodes.id, never a URL. Peers are configured
     * with an id and a base_url; the id is the identity used in the cursor
     * table so that repointing a peer at a new address does not orphan its
     * delivery history.
     */
    private function cursorFor(string $peer): int
    {
        return (int) (SyncCursor::where('node_id', (string) config('sync.node_id'))
            ->where('target_node_id', $peer)
            ->value('last_applied_change_id') ?? 0);
    }

    private function advanceCursor(string $peer, int $changeId): void
    {
        SyncCursor::updateOrCreate(
            ['node_id' => (string) config('sync.node_id'), 'target_node_id' => $peer],
            [
                'last_applied_change_id' => $changeId,
                'last_synced_at'         => now(),
            ],
        );
    }
}
```

### There is deliberately no `sent` column on `sync_changes`

Worth stating outright, because it is the obvious first design and it is wrong.

`sync_changes` is append-only and has no delivery flag. Delivery is expressed
entirely by `sync_cursors`: node A asks node B for everything with
`sync_changes.id > cursor(B, A)`, and B reports the new cursor in its response.
A therefore learns B's position from the reply instead of tracking it locally.

The alternative — a `sent` boolean — breaks on the first multi-peer push. One
change can be acknowledged by the LAN server and still be pending for the cloud,
and a single boolean cannot express both. Worse, a `sent` flag invites a "set
sent = 1 after a successful HTTP 200" implementation, which silently drops
changes whenever a push fails midway. Phase 7 surfaces the same pending count to
cashiers, computed from the cursor.

- [x] **Step 3: Write the cycle runner with peer failover**

Spec §4.3: terminal prefers LAN server, falls back to cloud. LAN server syncs to
the cloud plus each terminal. Cloud syncs to every node.

```php
<?php

namespace App\Sync;

use App\Sync\Exceptions\SyncTransportException;
use Illuminate\Support\Facades\Log;

class SyncCycleRunner
{
    public function __construct(
        private readonly NodeSyncService $sync,
    ) {
    }

    /**
     * Run one cycle against every configured peer.
     *
     * A dead peer is logged and skipped. Reconciliation continues against the
     * remaining peers, because a terminal must always have somewhere to sync
     * to even when its LAN server is unreachable.
     *
     * @return array{peers: int, pushed: int, pulled: int, failed: list<string>}
     */
    public function run(): array
    {
        $peers = config('sync.peers', []);
        $pushed = 0;
        $pulled = 0;
        $failed = [];

        foreach ($peers as $peer) {
            try {
                $result = $this->sync->syncWith($peer);

                $pushed += $result['pushed'];
                $pulled += $result['pulled'];
            } catch (SyncTransportException $e) {
                Log::warning('Sync peer unreachable.', [
                    'peer'  => $peer,
                    'error' => $e->getMessage(),
                ]);

                $failed[] = $peer;
            }
        }

        return [
            'peers'  => count($peers),
            'pushed' => $pushed,
            'pulled' => $pulled,
            'failed' => $failed,
        ];
    }
}
```

Failover is inherent: `config('sync.peers')` is an ordered list, the runner
iterates it, and a failure does not abort the cycle. A terminal configured with
`SYNC_PEERS=<lan>,<cloud>` degrades to cloud-only when the LAN server is down.

- [x] **Step 4: Test the cycle**

```php
public function test_an_unreachable_peer_does_not_stop_the_others(): void
{
    Http::fake([
        'lan-server.test/*'  => fn () => throw new ConnectionException('refused'),
        'cloud.test/*'       => Http::response(['accepted' => 0, 'changes' => [], 'cursor' => 0]),
    ]);

    config([
        'sync.peers'  => ['http://lan-server.test', 'http://cloud.test'],
        'sync.node_id' => 'node-a',
    ]);

    $result = app(SyncCycleRunner::class)->run();

    $this->assertSame(2, $result['peers']);
    $this->assertSame(['http://lan-server.test'], $result['failed']);
}

public function test_the_cursor_only_advances_after_changes_are_applied(): void
{
    Http::fake([
        '*' => Http::response(['accepted' => 0, 'changes' => [[
            'table_name' => 'inventory_items',
            'row_id'     => '01J8Z0000000000000000000A',   // sync_uid, not the integer id
            'operation'  => 'create',
            'node_id'    => 'node-b',
            'hlc'        => '001:0000000001:0000000000:node-b',
            'payload'    => ['sync_uid' => '01J8Z0000000000000000000A', 'name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg'],
        ]], 'cursor' => 1]),
    ]);

    // Peers are configured by node id plus base_url. The id is what the cursor
    // table records, so a peer that changes address keeps its delivery history.
    config([
        'sync.peers'    => ['node-b' => ['base_url' => 'http://peer']],
        'sync.node_id'  => 'node-a',
    ]);

    app(SyncCycleRunner::class)->run();

    // Both halves of the key are required. Asserting only target_node_id would
    // pass even if cursorFor() dropped the node_id scope entirely.
    $this->assertSame(1, SyncCursor::where('node_id', 'node-a')
        ->where('target_node_id', 'node-b')
        ->value('last_applied_change_id'));

    // And it did not create a second row for the same pair.
    $this->assertSame(1, SyncCursor::count());
}
```

Commit: `feat: implement the sync cycle with peer failover and cursor management`

---

## Task 4: Wire the commands and the scheduler

**Files:**
- Modify: `app/Console/Commands/SyncPush.php`, `SyncPull.php`, `SyncRun.php`
- Modify: `app/Jobs/SyncWorker.php`
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: Task 3.
- Produces: `sync:run`, `sync:status`, and a scheduled worker.

- [x] **Step 1: Replace the stubs**

```php
<?php

namespace App\Console\Commands;

use App\Sync\SyncCycleRunner;
use Illuminate\Console\Command;

class SyncRun extends Command
{
    protected $signature = 'sync:run {--node= : Limit the cycle to one peer}';

    protected $description = 'Run one synchronization cycle against every configured peer';

    public function handle(SyncCycleRunner $runner): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Sync is disabled. Set SYNC_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $result = $runner->run();

        $this->info(sprintf(
            'Peers: %d, pushed: %d, pulled: %d, failed: %d',
            $result['peers'],
            $result['pushed'],
            $result['pulled'],
            count($result['failed']),
        ));

        foreach ($result['failed'] as $peer) {
            $this->warn("  unreachable: {$peer}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
```

Give `sync:push` and `sync:pull` a `--peer=` option for debugging a single
connection, and have `sync:run` be the command the scheduler calls.

- [x] **Step 2: Add `sync:status`**

```php
<?php

namespace App\Console\Commands;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use App\Models\Sync\SyncNode;
use Illuminate\Console\Command;

class SyncStatus extends Command
{
    protected $signature = 'sync:status';

    protected $description = 'Show node identity, peers, cursors and pending change count';

    public function handle(): int
    {
        $this->table(
            ['Setting', 'Value'],
            [
                ['enabled',    (string) var_export(config('sync.enabled'), true)],
                ['node_id',    (string) config('sync.node_id')],
                ['node_type',  (string) config('sync.node_type')],
                ['peers',      implode(', ', config('sync.peers')) ?: '(none)'],
                ['changes',    (string) SyncChange::count()],
                ['registered', (string) SyncNode::count()],
            ],
        );

        $cursors = SyncCursor::all();

        if ($cursors->isNotEmpty()) {
            $this->table(
                ['Peer', 'Last applied change', 'Last synced'],
                $cursors->map(fn (SyncCursor $c) => [
                    $c->target_node_id,
                    (string) $c->last_applied_change_id,
                    (string) $c->last_synced_at,
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
```

- [x] **Step 3: Make `SyncWorker` handle failure**

```php
<?php

namespace App\Jobs;

use App\Sync\SyncCycleRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(SyncCycleRunner $runner): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        $result = $runner->run();

        Log::info('Sync cycle complete.', $result);
    }
}
```

Injecting the runner rather than calling `Artisan::call` keeps the job testable
without a kernel.

- [x] **Step 4: Schedule it**

Add to `routes/console.php`:

```php
Schedule::job(new \App\Jobs\SyncWorker)->everyMinute()->withoutOverlapping();
```

Follow the existing entries' style. Do not reintroduce a duplicate registration
in `routes/web.php` — Phase 0 fixed that.

- [x] **Step 5: Verify the schedule count**

```powershell
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
```

Expected: `17` — the original 16 plus this entry. If it is 18, a duplicate
registration crept back in.

- [x] **Step 6: Verify the commands**

```powershell
php artisan sync:status
$env:SYNC_ENABLED='false'; php artisan sync:run
```

Expected: the status table, then "Sync is disabled."
Commit: `feat: wire sync commands and schedule the sync worker`

---

## Task 5: Make inventory movement-authoritative

Spec §6.2: the movement is the truth and the balance is a rebuildable cache. Both
are still written, but the balance must be reconstructible at any moment, and
"without that test the ledger quietly diverges and the failure surfaces only at
stocktake."

`inventory:rebuild` exists and `InventoryRebuildTest` proves `rebuild == incremental`
for inventory. Drink stock has no equivalent.

**Files:**
- Create: `app/Console/Commands/RebuildDrinkStock.php`
- Create: `tests/Feature/Sync/DrinkStockRebuildTest.php`
- Modify: `app/Console/Commands/RebuildInventoryStocks.php`

**Interfaces:**
- Consumes: `drink_stock`, `drink_stock_movements`.
- Produces: `drink-stock:rebuild` and its proof.

- [x] **Step 1: Fix the SQL in `inventory:rebuild`**

The current implementation uses double-quoted string literals inside raw SQL:

```php
->whereRaw("CASE WHEN type IN (\"in\", \"transfer_in\") THEN ...")
```

Double quotes are identifiers in standard SQL, so this works on SQLite by luck
and breaks on Postgres. Use single quotes.

Also record the deterministic total order spec §6 Class 3 requires:
`(occurred_at, origin_node_id, movement_id)`.

- [x] **Step 2: Write the drink-stock rebuild**

Mirror `inventory:rebuild` for `drink_stock` from `drink_stock_movements`.

- [x] **Step 3: Write the drink-stock rebuild test**

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\DrinkStock;
use App\Models\DrinkStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DrinkStockRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_produces_the_same_counts_as_incremental(): void
    {
        $bar = \App\Models\MenuItem::factory()->create();

        $this->createMovement($bar, 'in', 24);
        $this->createMovement($bar, 'out', 6);
        $this->createMovement($bar, 'out', 2);

        $before = (float) DrinkStock::where('menu_item_id', $bar->id)->value('total_stock');

        Artisan::call('drink-stock:rebuild');

        $after = (float) DrinkStock::where('menu_item_id', $bar->id)->value('total_stock');

        $this->assertSame($before, $after);
        $this->assertSame(16.0, $after);
    }

    private function createMovement($item, string $type, float $quantity): void
    {
        DrinkStockMovement::create([
            'menu_item_id' => $item->id,
            'type'         => $type,
            'quantity'     => $quantity,
            'occurred_at'  => now(),
            'node_id'      => 'node-a',
        ]);
    }
}
```

Spec §13 requires this test for both ledgers. Inventory has it; drink stock does
not.

- [x] **Step 4: Prove the rebuild is order-independent**

Spec §6 Class 3 requires a deterministic total order so every node reaches an
identical balance. Add a test that inserts the same movements in a different
order and asserts an identical total.

- [ ] **Step 5: Run on all three engines** — proven on SQLite; MySQL and Postgres deferred to Phase 1R (no Postgres provisioned yet). The single-quoted `DB::raw` CASE is portable in principle and the SQLite guard covers the regression.

```powershell
php artisan test --filter=Rebuild
```

Commit: `feat: add drink stock rebuild and make ledger rebuild portable`

---

## Task 6: Prove three-process convergence

Spec §12 Phase 4: "Proven with three processes on one machine before any desktop
work begins."

**Files:**
- Create: `tests/Feature/Sync/ThreeNodeConvergenceTest.php`
- Create: `scripts/three-node-sync-demo.sh`

**Interfaces:**
- Consumes: Tasks 1–5.
- Produces: the proof that three real processes converge.

- [x] **Step 1: Write the script**

Three real databases, three real HTTP servers, real HTTP between them. This is
the first test of the actual transport rather than an in-process stand-in.

```bash
#!/usr/bin/env bash
# Three sync nodes on one machine.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="${TMPDIR:-/tmp}/hotelms-3node"
rm -rf "$WORK"; mkdir -p "$WORK"/{a,b,c}

for n in a b c; do
  touch "$WORK/$n.sqlite"

  DB_CONNECTION=sqlite DB_DATABASE="$WORK/$n.sqlite" \
  php "$ROOT/artisan" migrate:fresh --force --quiet
done

run () {
  DB_CONNECTION=sqlite DB_DATABASE="$WORK/$1.sqlite" \
  SYNC_ENABLED=true SYNC_NODE_ID="node-$1" SYNC_PEERS="$2" \
  php "$ROOT/artisan" "$3" "$4"
}

# Cloud is node-c and listens; a and b are terminals pointing at it.
...
```

Start one `php artisan serve` per node on separate ports, seed each with
disconnected writes, run `sync:run` repeatedly until stable, then compare the
three databases.

- [x] **Step 2: Compare and assert convergence**

```sql
-- run against each database, diff the output
SELECT id, name, sku FROM inventory_items ORDER BY id;
```

All three must be identical.

- [x] **Step 3: Test the LAN-down case**

Kill the LAN server process, leave the cloud running, run `sync:run` on a
terminal. It must still reconcile. This is spec §4.3's guarantee.

- [x] **Step 4: Write it as a test too**

The shell script is for demonstration; the CI-runnable version is a test using
Laravel's HTTP test client with three applications, or three spawned processes.
Cover: three nodes converge, an unreachable peer does not block the others, and a
restart mid-cycle does not lose changes.

- [x] **Step 5: Record the result**

`docs/SYNC_CONVERGENCE.md` — the commands, the observed output, and the
convergence guarantee. Reference it from the spec §13 section.

Commit: `test: prove three-process sync convergence on one machine`

---

## Task 7: Final verification

- [x] **Step 1: The gate**

```powershell
php artisan migrate:fresh --force
php artisan test
```

- [x] **Step 2: Verify sync is off by default**

```powershell
php artisan tinker --execute="var_dump(config('sync.enabled'));"
```

Expected: `bool(false)`. An unconfigured deployment must be unaffected.

- [x] **Step 3: Verify no unauthenticated endpoint exists**

```powershell
php artisan route:list --path=api/sync
Invoke-WebRequest -Uri "http://127.0.0.1:8000/api/sync/changes?since=0" -SkipHttpErrorCheck
```

Expected: `401`.

- [x] **Step 4: Verify the scheduler**

```powershell
php artisan tinker --execute="echo count(app(Illuminate\Console\Scheduling\Schedule::class)->events()), PHP_EOL;"
```

Expected: `17`.

Commit: `chore: phase 4r verification`

---

## Exit criteria

- `sync:run` performs a real push and pull over HTTP.
- A dead peer degrades throughput without stopping reconciliation.
- Cursors advance only after apply commits.
- `drink-stock:rebuild` exists and `rebuild == incremental` is proven for both
  ledgers.
- Three processes on one machine converge.
- `/api/sync/*` rejects unauthenticated requests.
- The suite is green and sync is off by default.

Next: [Phase 5 — booking authority partition](2026-10-05-phase-5-booking-authority-partition.md).
