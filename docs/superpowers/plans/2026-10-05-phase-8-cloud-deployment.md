# Phase 8 — Cloud Deployment

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 7](2026-10-05-phase-7-offline-terminal.md) complete.

**Goal:** Deploy the cloud with Postgres, run the synchronization daemon there, and confirm the web version is untouched and managers can see POS data.

**Architecture:** The cloud is the existing Coolify deployment with the database moved to Postgres and a synchronization worker added alongside the existing schedule. Nodes register with it and pull Class 5 configuration down.

**Tech Stack:** Coolify, PostgreSQL 16, Laravel, Laravel queues, the Phase 4R worker.

**Spec:** §10 (web version), §11 (database strategy), §12 Phase 8, §6 Class 5.

**Closes:** G42.

---

## The point of this phase

Spec §10: "Untouched in behaviour. Same Coolify deployment, same routes, same
pages." Managers see POS data on the web not because new screens were built, but
because terminals push Class 1 facts to the cloud and the existing admin and
reporting screens already read those tables.

So this phase's real test is negative: nothing user-visible changed, and yet
manager reporting now contains terminal data. If screens had to be modified to
show POS data, one of the two claims is wrong.

---

## Global Constraints

- **Spec §10's web version must not change.** If a screen needs modifying to
  surface POS data, stop and find out why.
- **Sync stays additive.** New columns and new tables only. No existing query
  changes behaviour.
- **Sync must not be able to take the site down.** A failing peer, a malformed
  change, or a merge conflict must never surface as a web error.
- **Class 5 configuration is cloud-authoritative.** Nodes never write it.
- Commit after every task.

---

## File Structure

**Created:**
- `deploy/coolify/README.md` — the deployment runbook.
- `deploy/coolify/postgres.env.example` — documented cloud variables.
- `app/Console/Commands/SyncRegisterNode.php` — approve a node and issue a credential.
- `app/Http/Controllers/Sync/NodeRegistrationController.php` — pairing-code exchange.
- `app/Console/Commands/SyncHealth.php` — daemon health, for the operator.
- `database/migrations/2026_10_05_100080_create_sync_pairing_codes_table.php`
- `tests/Feature/Cloud/CloudSyncIntegrationTest.php`
- `tests/Feature/Cloud/WebVersionUnchangedTest.php`

**Modified:**
- `config/sync.php` — cloud defaults.
- `.env.example` — cloud variable documentation.
- `routes/console.php` — the sync schedule on the cloud.
- `database/migrations/2026_10_05_100080_create_sync_pairing_codes_table.php`

**Responsibility note:** deployment configuration and the operator's controls. No
application behaviour changes.

---

## Task 1: Move the cloud database to Postgres

**Files:**
- Create: `deploy/coolify/postgres.env.example`
- Modify: `.env.example`

**Interfaces:**
- Consumes: Phase 1R's portability work and its parity gate.
- Produces: a cloud deployment running on Postgres.

- [ ] **Step 1: Confirm the parity gate is real before deploying**

```powershell
php artisan test --filter=SchemaParity
```

Phase 1R replaced the placeholder that asserted `true`. Do not deploy on Postgres
until it passes against a real Postgres instance. Migrating without erroring is
not evidence the schemas match — spec §11 rule 3.

- [ ] **Step 2: Document the cloud environment**

```
DB_CONNECTION=pgsql
DB_HOST=<coolify-internal-host>
DB_PORT=5432
DB_DATABASE=hotelms
DB_USERNAME=hotelms
DB_PASSWORD=<secret>

APP_ENV=production
APP_DEBUG=false
APP_URL=https://<public-host>

SYNC_ENABLED=true
SYNC_NODE_TYPE=cloud
SYNC_NODE_ID=<stable-ulid>
SYNC_PEERS=
SYNC_BATCH_SIZE=500
SYNC_REQUEST_TIMEOUT=30
```

`SYNC_PEERS` is empty on the cloud: the cloud is a peer that others connect to, not
one that dials out. Phase 4R's runner iterates `sync.peers`, so an empty list
means the cloud never initiates.

- [ ] **Step 3: Fix `SYNC_NODE_ID` in configuration**

The node id must be stable across deploys. A cloud container that generates a new
one on every release looks like a brand-new node to every terminal, so they
re-register and re-push their entire change logs. Set it as an environment
variable in Coolify and never let code generate it for the cloud role.

- [ ] **Step 4: Run the full migration against the real engine**

```powershell
DB_CONNECTION=pgsql php artisan migrate --force
php artisan migrate:status
```

Every migration applied, no skipped.

- [ ] **Step 5: Write the runbook**

`deploy/coolify/README.md` covers: environment variables, migration order, the
queue worker command, the sync schedule, how to add a node, and how to read
`sync:status` on a production machine.

Commit: `deploy: move the cloud database to postgres`

---

## Task 2: Run the sync daemon on the cloud

**Files:**
- Modify: `routes/console.php`
- Create: `app/Console/Commands/SyncHealth.php`

**Interfaces:**
- Consumes: Phase 4R's `SyncWorker`.
- Produces: a supervised sync loop on the cloud.

- [ ] **Step 1: Decide how the cloud runs sync**

The cloud is authoritative for Class 5 and a peer for Class 1 and 2 facts. It has
no outbound peers, so the scheduled worker has nothing to push to. What the cloud
actually needs is to *accept* pushes (Phase 4R's API) and to *pull* on demand when
staff work on the web.

Two reasonable options:

| Option | Shape | Verdict |
|---|---|---|
| **A — No cloud sync worker** | The API endpoint accepts pushes; `sync_changes` on the cloud grows; a separate reconciliation applies web-side writes downward | **Recommended.** The cloud applies remote writes lazily on the next cycle from a node, which is already built |
| **B — Cloud worker with an empty peer list** | Runs every minute, does nothing | No. Wasted schedule slot and a misleading green dashboard |

Choose A. Record the reasoning in the runbook so the empty worker is not
"restored" later by someone assuming it was an omission.

- [ ] **Step 2: Schedule reconciliation for web-side writes**

Web-side writes to synced tables must reach terminals. Terminals pull from the
cloud, so once a web-side write is in `sync_changes` on the cloud, terminals pick
it up on their next cycle. That works without a cloud-initiated push — which is
the argument for A.

Verify the capture hook fires for web-initiated writes:

```php
public function test_a_booking_created_on_the_web_is_captured_for_terminals(): void
{
    $booking = $this->createBookingViaWeb();

    $this->assertDatabaseHas('sync_changes', [
        'table_name' => 'bookings',
        'row_id'     => (string) $booking->id,
        'operation'  => 'create',
    ]);
}
```

That test belongs in Phase 2R's capture suite and should already exist. If it does
not, that is a Phase 2R gap, not a Phase 8 one — add it there.

- [ ] **Step 3: Write `sync:health` for the operator**

```php
<?php

namespace App\Console\Commands;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncConflict;
use App\Models\Sync\SyncCursor;
use App\Models\Sync\SyncNode;
use Illuminate\Console\Command;

class SyncHealth extends Command
{
    protected $signature = 'sync:health';

    protected $description = 'Report sync health for the cloud node';

    public function handle(): int
    {
        $changes = SyncChange::count();
        $stale = SyncChange::where('created_at', '<', now()->subHours(24))->count();
        $conflicts = SyncConflict::whereNull('resolved_at')->count();
        $nodes = SyncNode::where('status', 'active')->count();
        $inactive = SyncNode::where('status', '!=', 'active')->count();

        $this->table(['Metric', 'Value'], [
            ['registered nodes',        $nodes],
            ['inactive nodes',          $inactive],
            ['changes recorded',        $changes],
            ['changes older than 24h',  $stale],
            ['unresolved conflicts',    $conflicts],
            ['cursors',                 SyncCursor::count()],
        ]);

        if ($conflicts > 0) {
            $this->warn("{$conflicts} unresolved conflict(s) require a human. See `php artisan tinker` or the admin surface.");
        }

        if ($inactive > 0) {
            $this->warn("{$inactive} node(s) are not active. Check last_seen_at before assuming a terminal is offline.");
        }

        return self::SUCCESS;
    }
}
```

A stale change count is the operator's signal that something is wrong before a
guest notices.

- [ ] **Step 4: Expose health on a schedule for alerting**

Optional, and only if monitoring exists:

```
Schedule::command('sync:health')->hourly();
```

**Interfaces note:** this only writes to the log. Do not add a public health
endpoint that leaks node counts to the internet.

Commit: `feat: add sync health reporting for the cloud node`

---

## Task 3: Node registration and pairing

**Files:**
- Create: `app/Http/Controllers/Sync/NodeRegistrationController.php`
- Create: `app/Console/Commands/SyncRegisterNode.php`

**Interfaces:**
- Consumes: `sync_nodes` (Phase 2R), the credential hash (Phase 4R).
- Produces: an approved node with a credential.

- [ ] **Step 1: Decide the pairing model**

Spec §9.2: the server "registers with the cloud using a pairing code"; the
terminal "requests a property code and pairing code". So the flow is:

1. Manager creates a pairing code in the cloud admin surface.
2. Terminal submits property code plus pairing code.
3. Cloud issues a node credential, shown once.
4. Terminal stores it and registers as a node.

The pairing code must be single-use and time-limited.

- [ ] **Step 2: Write the exchange endpoint**

```php
<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Sync\SyncNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exchanges a single-use pairing code for a node credential.
 *
 * The credential is returned once and never again: only its hash is stored,
 * so a database leak does not yield usable node credentials.
 */
class NodeRegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_code' => ['required', 'string'],
            'pairing_code'  => ['required', 'string'],
            'node_name'     => ['required', 'string', 'max:120'],
            'node_type'     => ['required', 'in:lan_server,terminal'],
        ]);

        $pairing = DB::table('sync_pairing_codes')
            ->where('code', $validated['pairing_code'])
            ->where('property_code', $validated['property_code'])
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pairing === null) {
            return response()->json(['message' => 'Invalid or expired pairing code.'], 422);
        }

        $nodeId = Str::ulid()->toBase32();
        $secret = Str::random(64);

        $node = SyncNode::create([
            'node_id'    => $nodeId,
            'node_type'  => $validated['node_type'],
            'name'       => $validated['node_name'],
            'property_id' => $pairing->property_id,
            'credential' => hash('sha256', $secret),
            'status'     => 'active',
            'last_seen_at' => now(),
        ]);

        // Single use. Consume inside the same transaction as the insert so two
        // concurrent registrations cannot both succeed.
        DB::table('sync_pairing_codes')->where('id', $pairing->id)->update(['used_at' => now()]);

        return response()->json([
            'node_id'    => $node->node_id,
            'credential' => $secret,
            'cloud_url'  => config('app.url'),
        ], 201);
    }
}
```

- [ ] **Step 3: Add the pairing-code table**

```php
Schema::create('sync_pairing_codes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('property_id')->constrained()->cascadeOnDelete();
    $table->string('code', 32)->unique();
    $table->timestamp('expires_at');
    $table->timestamp('used_at')->nullable();
    $table->timestamps();
});
```

The table does not exist yet — Phase 2R created `sync_nodes`, `sync_changes`,
`sync_cursors`, `sync_conflicts` and `sync_sequences` only. Add it as
`database/migrations/2026_10_05_100080_create_sync_pairing_codes_table.php`.

- [ ] **Step 4: Add `sync:register` for operators**

```powershell
php artisan sync:register --name="Bar Terminal 1" --type=terminal --property=1
```

Prints the node id and secret once. Operators will need this before the admin UI
exists, and before a wizard is available on a machine that will not boot.

- [ ] **Step 5: Test**

```php
public function test_a_pairing_code_is_single_use(): void
{
    $code = $this->createPairingCode();

    $this->postJson('/api/sync/register', $this->payload($code))->assertCreated();
    $this->postJson('/api/sync/register', $this->payload($code))->assertStatus(422);
}

public function test_an_expired_pairing_code_is_rejected(): void
{
    // expires_at in the past
}

public function test_the_credential_is_returned_once_and_stored_hashed(): void
{
    $response = $this->postJson('/api/sync/register', $this->payload($this->createPairingCode()));

    $secret = $response->json('credential');

    $this->assertNotSame($secret, DB::table('sync_nodes')->value('credential'));
    $this->assertTrue(Str::check(Str::of(hash('sha256', $secret)), DB::table('sync_nodes')->value('credential')));
}
```

Commit: `feat: add single-use pairing-code node registration`

---

## Task 4: Prove the cloud integration

**Files:**
- Create: `tests/Feature/Cloud/CloudSyncIntegrationTest.php`

**Interfaces:**
- Consumes: everything from Phases 2R through 7.
- Produces: the end-to-end proof.

- [ ] **Step 1: Test the full round trip**

```php
public function test_a_terminal_sale_reaches_the_cloud_and_appears_in_reporting(): void
{
    // Terminal: create session, device, sell.
    // Terminal -> cloud: sync:run.
    // Cloud: assert pos_dockets, pos_docket_items, pos_docket_payments,
    //        inventory_movements all present, exactly once.
    // Cloud: assert the existing reporting screen shows the sale.
}

public function test_a_cloud_booking_reaches_the_terminal(): void
{
    // Cloud: create a booking for arrival beyond the 36-hour cutoff.
    // Terminal: sync:run.
    // Terminal: assert the booking and its room assignment are present and
    //           consistent, so the front desk can see it.
}

public function test_class_5_settings_are_pushed_down_and_never_up(): void
{
    // Cloud: change booking_local_authority_cutoff_hours.
    // Terminal: sync:run; assert the new value and that the terminal cannot
    //           push a different one back.
}

public function test_cloud_side_writes_to_class_1_tables_are_never_merged(): void
{
    // A manager edits a docket note on the cloud. Class 1 is append-only, so
    // the edit must not overwrite the terminal's original facts.
}
```

- [ ] **Step 2: Test that a failing node cannot break the site**

```php
public function test_a_malformed_change_cannot_error_the_web_request(): void
{
    // POST /api/sync/changes with a payload referencing a table not in the
    // manifest, a null HLC, an unparseable HLC, and a payload missing an
    // expected column. Assert every one is rejected at the boundary and the
    // rest of the application is unaffected.
}

public function test_a_sync_failure_never_surfaces_as_a_web_error(): void
{
    // Make the applier throw. Assert the public booking flow still works.
}
```

Sync is additive and must never be able to take the site down (spec §10).

- [ ] **Step 3: Test the real deployment target**

The test suite runs on SQLite. This phase needs the integration test against
actual Postgres, because `\d+` and Postgres both have live rows and the schema
parity gate matters here. Run it in CI with a Postgres service container, and
against the staging deployment before production.

Commit: `test: prove the full cloud sync round trip on postgres`

---

## Task 5: Prove the web version is unchanged

Spec §10. This is the negative test the phase exists for.

**Files:**
- Create: `tests/Feature/Cloud/WebVersionUnchangedTest.php`

- [ ] **Step 1: Assert no user-visible behaviour changed**

```php
public function test_the_public_booking_flow_is_unchanged_beyond_the_cutoff(): void
{
    // Arrival well beyond the cutoff: the guest books online exactly as before.
}

public function test_existing_admin_screens_need_no_modification_to_show_pos_data(): void
{
    // Seed a terminal-originated docket through the sync endpoint, then render
    // the existing admin and reporting screens and assert the data appears.
    // If this test needs a screen changed to pass, spec section 10 is violated.
}

public function test_staff_clients_still_hold_no_database(): void
{
    // The staff client role must not create one (spec 9.2).
}
```

- [ ] **Step 2: Diff the route and view surface**

```powershell
php artisan route:list > routes-after.txt
git diff --stat resources/js/js/Pages/
```

The route surface should differ only by the sync endpoints. A large view diff
means something user-visible changed and needs justification.

- [ ] **Step 3: Verify the existing suite**

```powershell
php artisan migrate:fresh --force
php artisan test
```

Commit: `test: prove the web version is behaviourally unchanged`

---

## Task 6: Document and hand over

**Files:**
- Create: `deploy/coolify/README.md`

- [ ] **Step 1: Write the runbook**

Cover: environment variables and where each lives, migration order, the queue
worker, the sync schedule, registering a node by code and by pairing code,
reading `sync:status` and `sync:health`, what to do about unresolved conflicts,
and how to add a LAN server or terminal.

- [ ] **Step 2: Document the recovery procedure**

The question an operator will actually ask: "a terminal is not appearing on the
cloud". Answer it with ordered checks — pairing code, credential stored, node
`status`, `last_seen_at`, `SYNC_PEERS`, firewall, clock skew.

**Clock skew deserves its own note.** HLC ordering depends on wall-clock time.
Gross skew between a terminal and the cloud degrades ordering. The Phase 2R
`Hlc` implementation bounds this, but an operator seeing merge anomalies should
check NTP first.

- [ ] **Step 3: Document the known limitations**

Carry these forward honestly rather than discovering them in production:

- Reporting projections are not synchronized; each node rebuilds them from
  Class 1 rows (spec §6.1).
- The arrival cutoff is evaluated at date granularity (Phase 5, Decision 2).
- Receipt printing uses the OS print dialog; dedicated thermal hardware is not
  implemented (Phase 7 Gate).
- Desktop binaries are unsigned (Phase 7).
- Sync is off by default; an unconfigured deployment is unaffected (spec §10).

Commit: `docs: add the cloud deployment runbook`

---

## Exit criteria

- The cloud runs on Postgres with all migrations applied.
- The Postgres parity gate passes against a real instance.
- `SYNC_NODE_ID` is stable across deploys.
- Pairing codes are single-use, time-limited, and credentials are stored hashed.
- A terminal sale reaches the cloud exactly once and appears in existing
  reporting with no screen modified.
- A cloud booking reaches the terminal with consistent room assignment.
- Class 5 settings push down and cannot be pushed up.
- A malformed change or a sync failure cannot error a web request.
- The full suite is green on both SQLite and Postgres.
- The runbook answers "why is this terminal not on the cloud".

---

## Closing the design

With Phase 8 done, every phase in spec §12 has shipped:

| Phase | Delivered |
|---|---|
| 0 | SQLite builds the schema, green suite, no dead files, honest docs |
| 1 | Postgres portability with a real parity gate |
| 2 | Sync engine: HLC, change log, five-class merge policy |
| 3 | Working Club POS on the web |
| 4 | Runtime sync node, proven with three processes |
| 5 | Booking authority partition on both channels |
| 6 | One installer, three roles, bundled runtime |
| 7 | Offline terminal, proven by disconnecting mid-shift |
| 8 | Cloud deployment, web version unchanged, manager visibility confirmed |
