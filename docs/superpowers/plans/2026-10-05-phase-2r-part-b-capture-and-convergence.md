# Phase 2R Part B — Change-Log Capture and Two-Node Convergence

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisite:** `2026-10-05-phase-2r-part-a-repair-schema-policies.md` complete.

**Goal:** Make the sync engine actually run. Implement change-log capture so every write to a syncable row stamps an HLC and appends a `sync_changes` row, apply remote changes without re-logging them, and prove convergence with two genuinely separate databases.

**Architecture:** `Syncable` hooks Eloquent writes and delegates to a single `SyncLogger` service. The logger stamps `sync_hlc`, mints the per-node sequence, and appends a full snapshot. A static applying-flag prevents a sync-applied write from logging a second change, which is what stops the infinite echo. `SyncApplier` consumes remote changes through the Part A policies.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite 3 (two file-backed databases), MySQL, PostgreSQL.

**Spec:** §5.2 (append-only change log), §5.3 (pure merge), §5.4 (idempotent application), §13 (two-node property test, idempotency test, golden fixture).

**Closes:** G2, G5, G11, G12, G43.

---

## The gap this closes

Right now nothing in `app/` writes `sync_hlc` and nothing appends to
`sync_changes`. The only two references in the entire codebase are in
`SyncChange`'s own `$table` and `$guarded` declarations. The 15
`add_sync_fields` migrations added three columns to fifteen tables that will stay
`NULL` forever, which is why the sync engine can appear "complete" by file
count while doing nothing.

`Syncable` is currently two accessors:

```php
public function getSyncId(): string { return (string) $this->id; }
public function getSyncTable(): string { return $this->getTable(); }
```

There is no boot hook, no HLC stamp, no change row. This part adds both.

---

## Global Constraints

- **One place appends to `sync_changes`.** `SyncLogger` and nothing else. Two writers means two sequence counters and an ordering bug.
- **No double-logging.** A write applied from a remote change must not append a new change. Guard with `SyncLogger::applyingRemote()`.
- **Full snapshots, not diffs** (spec §5.2). `payload` is the complete post-write row.
- **Disabled by default.** `SYNC_ENABLED=false` means an unconfigured deployment behaves exactly as it does today. Spec §10: synchronization is purely additive.
- **Model resolution must be a whitelist.** Never derive a class name from a table name in incoming data.
- Commit after every task. Focused commits.
- Do not modify `.env`. Use inline environment variables.

---

## File Structure

**Created:**
- `config/sync.php` — node identity, peers, enabled flag, batch size.
- `app/Sync/SyncLogger.php` — the only writer to `sync_changes`.
- `app/Sync/SyncApplier.php` — applies a remote change locally.
- `app/Sync/SyncEntityRegistry.php` — whitelisted entity-key to model-class map.
- `tests/Support/TwoNodeSyncHarness.php` — two real file-backed SQLite databases.
- `tests/Feature/Sync/ChangeLogCaptureTest.php`
- `tests/Feature/Sync/IdempotencyTest.php`
- `tests/Feature/Sync/TwoNodeConvergenceTest.php`
- `tests/Fixtures/sync/golden-scenario.json` — the spec §13 golden fixture.

**Rewritten:**
- `tests/Feature/Sync/TwoDbSyncTest.php` — currently asserts that the same
  in-memory handle is non-null twice.
- `tests/Feature/Sync/SyncEngineTest.php` — currently only round-trips an HLC
  string and asserts an accessor.

**Modified:**
- `app/Sync/Syncable.php` — add boot hooks, HLC stamping, capture.
- `app/Models/Sync/SyncChange.php` — casts and fillable.

**Responsibility note:** this part makes writes observable and proves two disconnected nodes converge. No transport, no HTTP, no CLI — that is Phase 4R.

---

## Task 1: Configure node identity

**Files:**
- Create: `config/sync.php`

**Interfaces:**
- Consumes: environment.
- Produces: `config('sync.enabled')`, `config('sync.node_id')`, `config('sync.node_type')`, `config('sync.peers')`, `config('sync.batch_size')`.

- [x] **Step 1: Write the config**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sync Enablement
    |--------------------------------------------------------------------------
    |
    | Off by default. An unconfigured deployment behaves exactly as it did
    | before sync existed. Spec section 10: synchronization is purely
    | additive and must not change existing behaviour.
    |
    */

    'enabled' => (bool) env('SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Node Identity
    |--------------------------------------------------------------------------
    |
    | A ULID minted at first setup and persisted thereafter. It must be
    | stable: changing it orphans every row this node has written and
    | breaks the last-write-wins tie-break.
    |
    */

    'node_id' => env('SYNC_NODE_ID'),

    'node_type' => env('SYNC_NODE_TYPE', 'terminal'),

    'property_id' => env('SYNC_PROPERTY_ID'),

    /*
    |--------------------------------------------------------------------------
    | Peers
    |--------------------------------------------------------------------------
    |
    | Comma-separated base URLs. Per spec section 4.3: a terminal prefers
    | the LAN server and falls back to the cloud, so a dead LAN server
    | degrades throughput rather than stopping reconciliation.
    |
    */

    'peers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SYNC_PEERS', ''))
    ))),

    'batch_size' => (int) env('SYNC_BATCH_SIZE', 500),

    'request_timeout' => (int) env('SYNC_REQUEST_TIMEOUT', 30),
];
```

- [x] **Step 2: Add the environment keys to `.env.example`**

```
SYNC_ENABLED=false
SYNC_NODE_ID=
SYNC_NODE_TYPE=terminal
SYNC_PROPERTY_ID=
SYNC_PEERS=
SYNC_BATCH_SIZE=500
```

Do not touch `.env` — it is untracked local state.

- [x] **Step 3: Verify the config resolves**

```powershell
php artisan tinker --execute="var_dump(config('sync.enabled'), config('sync.node_type'), config('sync.batch_size'));"
```

Expected: `bool(false)`, `'terminal'`, `500`.
Commit: `feat: add sync node configuration`

Resolved during execution: Part A Task 3's `SyncScope::mayEmit()` sample read
`config('sync.node_kind')`, a key nothing ever defines. This task defines it as
`sync.node_type`, and Phase 4R, 5, 6 and 8 all read that name, so `node_kind`
was a typo in the Part A sample. `SyncScope` and its test were corrected to
`sync.node_type` and the Part A plan was fixed at source. Without that, the
read-only gate would have silently stayed open.

---

## Task 2: Implement the capture service

This is the architectural spine. Everything downstream depends on it.

**Files:**
- Create: `app/Sync/SyncLogger.php`

**Interfaces:**
- Consumes: Task 1 config, Part A `SyncScope`.
- Produces:
  - `SyncLogger::capture(Model $row, string $operation): ?SyncChange`
  - `SyncLogger::applyingRemote(callable $callback): mixed`
  - `SyncLogger::isApplying(): bool`
  - `SyncLogger::nextSequence(): int`

- [x] **Step 1: Write the service**

```php
<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SyncLogger
{
    /**
     * True while a remote change is being applied. Suppresses capture so a
     * synchronised write does not append a second change row, which would
     * echo back to the peer forever.
     */
    private static bool $applying = false;

    public static function applyingRemote(callable $callback): mixed
    {
        $previous = self::$applying;

        self::$applying = true;

        try {
            return $callback();
        } finally {
            self::$applying = $previous;
        }
    }

    public static function isApplying(): bool
    {
        return self::$applying;
    }

    /**
     * Append one change row for a write to a syncable model.
     *
     * Returns null when sync is disabled, when a remote change is being
     * applied, when the entity has no registered policy, or when the table is
     * read-only for this node kind — in all four cases appending a change
     * would be wrong.
     */
    public static function capture(Model $row, string $operation): ?SyncChange
    {
        if (! config('sync.enabled') || self::$applying) {
            return null;
        }

        $entityKey = $row->getSyncTable();

        if (! SyncScope::has($entityKey)) {
            return null;
        }

        if (! SyncScope::mayEmit($entityKey)) {
            return null;
        }

        return SyncChange::create([
            'table_name' => $entityKey,
            'row_id'     => (string) $row->getSyncId(),
            'operation'  => $operation,
            'node_id'    => (string) config('sync.node_id'),
            'hlc'        => (string) $row->sync_hlc,
            'sequence'   => self::nextSequence(),
            'payload'    => json_encode($row->attributesToArray()),
        ]);
    }

    /**
     * Per-node monotonic sequence, per spec section 5.4.
     *
     * Allocated under a row lock on a per-node counter row, NOT by reading
     * max(sequence) + 1. max+1 is a race: two concurrent writes on one node both
     * read the same max, both mint the same sequence, and the (node_id, sequence)
     * unique index turns a lost write into a failed sale.
     *
     * The counter row is created by the same migration as sync_changes.
     */
    public static function nextSequence(): int
    {
        $nodeId = (string) config('sync.node_id');

        return DB::transaction(function () use ($nodeId): int {
            $counter = DB::table('sync_sequences')
                ->where('node_id', $nodeId)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                DB::table('sync_sequences')->insert(['node_id' => $nodeId, 'sequence' => 1]);

                return 1;
            }

            $next = ((int) $counter->sequence) + 1;

            DB::table('sync_sequences')
                ->where('node_id', $nodeId)
                ->update(['sequence' => $next]);

            return $next;
        });
    }
}
```

The `$previous` save in `applyingRemote` rather than a bare reset matters: nested
or exception-interrupted application must restore the prior state, not force it
to false.

- [x] **Step 2: Fix `SyncChange` casts and fillable**

The current model is `protected $guarded = []` with no casts, so `payload`,
`meta` and any JSON columns come back as raw strings. Add explicit casts.

```php
<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;

class SyncChange extends Model
{
    protected $table = 'sync_changes';

    protected $guarded = [];

    protected $casts = [
        'payload'   => 'array',
        'sequence'  => 'integer',
        'applied_at' => 'datetime',
    ];
}
```

A `payload` cast to array is what lets the applier use it directly instead of
`json_decode` on every row.

- [x] **Step 3: Verify the logger in isolation**

```powershell
php artisan tinker --execute="
config(['sync.enabled' => true, 'sync.node_id' => 'probe-node']);
echo App\Sync\SyncLogger::nextSequence() . PHP_EOL;
echo App\Sync\SyncLogger::nextSequence() . PHP_EOL;
echo var_export(App\Sync\SyncLogger::isApplying(), true) . PHP_EOL;
"
```

Expected: `1`, `2`, `false`. Commit: `feat: add sync change logger`

Resolved during execution — four defects in the sample:

1. `payload` was built with `json_encode(...)` while `SyncChange` casts payload
   to array. Laravel json_encodes on set for that cast, so the value would have
   been stored double-encoded and read back as a string. Passed as an array
   instead; verified the raw column holds a single-encoded object.
2. `capture()` logged no guard for an empty `sync.node_id`. Every unconfigured
   node would write `node_id` = `''`, and two such nodes mint interleaved
   sequences under that one key, corrupting the receiving cursor with no error
   locally. A fifth null-return was added, and `nextSequence()` now takes the
   already-resolved id so it is read exactly once.
3. `nextSequence()` used a bare insert to create the counter row. On the very
   first capture two concurrent transactions can both find no row and one
   fails on the primary key. Replaced with `insertOrIgnore` followed by
   `lockForUpdate`.
4. `nextSequence()` was documented as `nextSequence(): int` in the interface
   list and is called with no arguments in Step 3 below, so it stays public
   and takes an optional node id rather than the required parameter the sample
   implied.

`SyncChange` casts were applied as written. Verified: sequences 1 then 2,
`isApplying()` false, `applyingRemote` restores prior state, payload round-trips
as an array, suite unchanged at 185 passed / 745 assertions.

---

## Task 3: Make the trait capture

**Files:**
- Modify: `app/Sync/Syncable.php`

**Interfaces:**
- Consumes: Task 2 `SyncLogger`, Part A `Hlc`.
- Produces: `Syncable::bootSyncable()` registering the hooks, and `Syncable::stampSyncMetadata(): void`.

- [x] **Step 1: Write the trait**

```php
<?php

namespace App\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Syncable
{
    /**
     * Register capture on write.
     *
     * `saved` rather than `creating`: the change row references the row, so
     * the row must exist first, and the HLC must be persisted onto the row
     * itself rather than held in memory.
     */
    public static function bootSyncable(): void
    {
        static::saved(function (Model $row): void {
            $row->stampSyncMetadata();
            SyncLogger::capture($row, $row->wasRecentlyCreated ? 'create' : 'update');
        });

        static::deleted(function (Model $row): void {
            SyncLogger::capture($row, 'delete');
        });
    }

    /**
     * Write a fresh HLC and this node's id onto the row.
     *
     * Persisted with saveQuietly so the HLC write does not itself re-enter
     * the `saved` hook and log a second change.
     */
    public function stampSyncMetadata(): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        $this->sync_hlc = Hlc::now((string) config('sync.node_id'))->toString();
        $this->sync_origin_node_id = (string) config('sync.node_id');

        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    public function getSyncId(): string
    {
        // The ULID in sync_uid, never the integer primary key.
        //
        // Part A added a nullable, unique sync_uid column to every synced domain
        // table and backfilled it. `id` is local to this engine and diverges
        // across nodes, so it cannot identify a row for sync purposes.
        return (string) ($this->sync_uid ?: Str::ulid()->toBase32());
    }

    public function getSyncTable(): string
    {
        return $this->getTable();
    }
}
```

`saveQuietly` is load-bearing. Without it, persisting the HLC re-triggers
`saved`, which captures again, which persists a new HLC — an infinite loop
inside a single save.

- [x] **Step 2: Apply the trait to the syncable models**

The models whose tables carry `sync_hlc` (all 15 from the Part A catalogue plus
`bookings`). Add `use Syncable;` and declare the columns fillable if the model
uses a fillable list.

```powershell
rg -l "class (PosDocket|PosDocketItem|PosDocketPayment|PosPrintLog|InventoryMovement|DrinkStockMovement|Payment|AuditLog|InventoryItem|InventoryLocation|RoomType|Room|StaffProfile|Booking|PosSession) " app/Models/
```

Verify each gained the trait. Do not add it to `InventoryStock` or `DrinkStock` —
Class 3 balances are recomputed, not synchronised as authoritative.

- [x] **Step 3: Write the capture test**

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use App\Sync\SyncLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeLogCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-a',
        ]);
    }

    public function test_writing_a_syncable_row_stamps_an_hlc(): void
    {
        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertNotNull($item->fresh()->sync_hlc);
        $this->assertSame('node-a', $item->fresh()->sync_origin_node_id);
    }

    public function test_writing_a_syncable_row_appends_one_change_row(): void
    {
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(1, SyncChange::count());

        $change = SyncChange::first();

        $this->assertSame('create', $change->operation);
        $this->assertSame('inventory_items', $change->table_name);
        $this->assertSame('node-a', $change->node_id);
        $this->assertIsArray($change->payload);
        $this->assertSame(1, $change->sequence);
    }

    public function test_updating_appends_a_second_change_with_an_incremented_sequence(): void
    {
        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $item->update(['name' => 'Jollof Rice']);

        $this->assertSame(2, SyncChange::count());
        $this->assertSame(2, SyncChange::orderBy('sequence')->first()->sequence === 1 ? 2 : 2);
        $this->assertSame([1, 2], SyncChange::orderBy('sequence')->pluck('sequence')->all());
    }

    public function test_capture_is_suppressed_while_applying_a_remote_change(): void
    {
        SyncLogger::applyingRemote(function (): void {
            InventoryItem::create(['name' => 'Remote', 'sku' => 'R-9', 'unit' => 'kg']);
        });

        $this->assertSame(0, SyncChange::count());
        $this->assertFalse(SyncLogger::isApplying());
    }

    public function test_capture_is_disabled_when_sync_is_off(): void
    {
        config(['sync.enabled' => false]);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(0, SyncChange::count());
        $this->assertNull($item->fresh()->sync_hlc);
    }

    public function test_stamping_the_hlc_does_not_append_a_second_change(): void
    {
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->assertSame(1, SyncChange::count());
    }
}
```

Drop the redundant third assertion in the second test; the fourth one is the
meaningful version. The point of the pair is that the sequence is monotonic and
that a save produces exactly one change row.

- [x] **Step 4: Run the capture test**

```powershell
php artisan test --filter=ChangeLogCaptureTest
```

Expected: all pass. Before this task these columns and the `sync_changes` table
are untouched by any application write.
Commit: `feat: capture sync changes on every write to a syncable row`

Resolved during execution — the assertion instruction above was miscounted and
the sample carried three defects:

Assertion note: "the redundant third assertion in the second test" points at
nothing. The tautology is `$this->assertSame(2, SyncChange::orderBy('sequence')
->first()->sequence === 1 ? 2 : 2);`, the second assertion of the *third* test,
which is always true whatever the sequence is. It was dropped; the `[1, 2]`
pluck assertion below it is the one that proves monotonicity.

1. `stampSyncMetadata()` had no scope guard. `SyncChange` uses this trait but
   `sync_changes` has no `sync_hlc`, `sync_origin_node_id` or `sync_uid`, so the
   first captured change would have issued an UPDATE naming a column that does
   not exist and failed outright - capture could never have worked at all. Both
   stamping and minting now return early unless the table is in the catalogue.
   The same guard covers a model given the trait without the matching columns.
2. `sync_uid` was never minted. `sync_uid` was added nullable with a unique
   index and no default, and no code wrote it, so `getSyncId()` fell back to a
   ULID generated on the spot and never persisted. `row_id` would have pointed
   at an identifier that exists nowhere, and every remote apply would miss.
   It is now minted on `creating` so it lands inside the INSERT itself, whether
   or not sync is enabled, and again before stamping as a safety net.
3. Stamping proceeded with an empty `sync.node_id`, producing an HLC with an
   empty node component and a change row under `node_id` = `''`. Two such nodes
   interleave sequences under one key and corrupt the receiving cursor. Stamp
   and capture now refuse together, so a row is never stamped without being
   logged.

`InventoryItem` was the only catalogue model without the trait; it has been
added. `InventoryStock`, `DrinkStock`, `Setting` and `Role` are excluded as the
plan requires.

The test file ships the plan's six cases plus four regression tests, one per
defect above.

Unresolved observation, deliberately not acted on: all fifteen synced tables
carry `sync_deleted_at`, and nothing in Tasks 2 or 3 writes it. It reads as
applier-side bookkeeping rather than capture-side, so Task 4 is where it
belongs. If the applier does not claim it there, raise it before Phase 4R.

---

## Task 4: Implement the applier

**Files:**
- Create: `app/Sync/SyncEntityRegistry.php`, `app/Sync/SyncApplier.php`
- Create: `tests/Feature/Sync/IdempotencyTest.php`

**Interfaces:**
- Consumes: Tasks 2–3, Part A policies.
- Produces: `SyncApplier::apply(SyncChange $change): void`, consumed by Phase 4R's pull path.

- [x] **Step 1: Whitelist entity keys to model classes**

Never derive a class name from a table name in incoming data — that would let a
hostile payload name an arbitrary class.

```php
<?php

namespace App\Sync;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\DrinkStockMovement;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\PosDocket;
use App\Models\PosDocketItem;
use App\Models\PosDocketPayment;
use App\Models\PosPrintLog;
use App\Models\PosSession;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit entity-key to model-class map.
 *
 * A table name arriving over the wire is untrusted input. Resolving it to a
 * class by string manipulation would let a payload name any class in the
 * application.
 */
final class SyncEntityRegistry
{
    /** @var array<string, class-string<Model>> */
    private const MAP = [
        'pos_dockets'          => PosDocket::class,
        'pos_docket_items'     => PosDocketItem::class,
        'pos_docket_payments'  => PosDocketPayment::class,
        'pos_print_logs'       => PosPrintLog::class,
        'inventory_movements'  => InventoryMovement::class,
        'drink_stock_movements' => DrinkStockMovement::class,
        'payments'             => Payment::class,
        'audit_logs'           => AuditLog::class,
        'inventory_items'      => InventoryItem::class,
        'inventory_locations'  => InventoryLocation::class,
        'room_types'           => RoomType::class,
        'rooms'                => Room::class,
        'staff_profiles'       => StaffProfile::class,
        'bookings'             => Booking::class,
        'pos_sessions'         => PosSession::class,
    ];

    /** @return class-string<Model> */
    public static function modelFor(string $entityKey): string
    {
        return self::MAP[$entityKey]
            ?? throw new \RuntimeException("No model registered for sync entity [{$entityKey}].");
    }
}
```

- [x] **Step 2: Implement the applier**

```php
<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
use Illuminate\Support\Facades\DB;

class SyncApplier
{
    /**
     * Apply one remote change locally.
     *
     * Wrapped in a transaction and run inside applyingRemote() so the write
     * is not re-logged as a local change.
     */
    public function apply(SyncChange $change): void
    {
        $payload = is_array($change->payload) ? $change->payload : [];
        $model = SyncEntityRegistry::modelFor($change->table_name);

        DB::transaction(function () use ($model, $change, $payload): void {
            SyncLogger::applyingRemote(function () use ($model, $change, $payload): void {
                // Identity is sync_uid, never the integer primary key.
                //
                // Part A widened sync_changes.row_id to a string and added a
                // sync_uid column to every synced domain table. Row id from the
                // origin node is the ULID; `id` is this node's own auto-increment
                // value and means something different on each node. Looking up
                // by `id` would either find the wrong row or, once the ids
                // diverge, create a duplicate.
                $uid = (string) ($payload['sync_uid'] ?? $change->row_id);

                $instance = $model::query()
                    ->where('sync_uid', $uid)
                    ->lockForUpdate()
                    ->first();

                if ($change->operation === 'delete') {
                    $instance?->delete();

                    return;
                }

                if ($instance === null) {
                    // First time this node has seen the row. The incoming payload
                    // carries the origin's sync_uid but not this engine's `id`,
                    // so it must not be inserted verbatim.
                    $attributes = $payload;
                    unset($attributes['id']);
                    $attributes['sync_uid'] = $uid;

                    $model::query()->create($attributes);

                    return;
                }

                // Preserve this node's own primary key; overwrite everything else
                // the change carries.
                $instance->fill(Arr::except($payload, ['id']))->save();
            });
        });
    }

    /**
     * Apply a batch, advancing a cursor only after the whole batch commits.
     *
     * @param  iterable<SyncChange>  $changes
     */
    public function applyBatch(iterable $changes, int $throughChangeId): void
    {
        foreach ($changes as $change) {
            $this->apply($change);
        }

        // Cursor advance belongs to Phase 4R, which owns sync_cursors.
    }
}
```

`fill()->save()` on an existing row is the idempotency mechanism: replaying the
same change produces the same row, because the payload is a full snapshot and
the write is a plain overwrite rather than an increment.

- [x] **Step 3: Write the idempotency test**

Spec §13: "Idempotency test — replay an entire change log twice; assert
byte-identical state."

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\Sync\SyncChange;
use App\Sync\SyncApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replaying_the_entire_log_twice_leaves_identical_state(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $item = InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);
        $item->update(['name' => 'Jollof Rice']);

        $changes = SyncChange::orderBy('sequence')->get();

        $this->assertGreaterThan(1, $changes->count());

        $before = InventoryItem::orderBy('id')->get()->toJson();

        $applier = app(SyncApplier::class);

        foreach ([1, 2] as $ignored) {
            foreach ($changes as $change) {
                $applier->apply($change);
            }
        }

        $this->assertSame($before, InventoryItem::orderBy('id')->get()->toJson());
        $this->assertCount(1, InventoryItem::all());
    }

    public function test_applying_a_change_does_not_append_a_new_change(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $count = SyncChange::count();

        app(SyncApplier::class)->apply(SyncChange::first());

        $this->assertSame($count, SyncChange::count());
    }
}
```

The second test is the echo guard. Without it, a pull would append changes that
the peer then pulls, forever.

- [x] **Step 4: Run the idempotency test**

```powershell
php artisan test --filter=IdempotencyTest
```

Commit: `feat: apply remote changes idempotently`

Resolved during execution — four defects:

1. `Arr::except()` was called with no `use Illuminate\Support\Arr`, so the
   update path would have thrown a class-not-found on its first remote change.
2. `fill()` honours `$fillable` and all fifteen syncable models declare one.
   `InventoryItem` does not list `quantity`, so a remote stock change would have
   been discarded silently and the nodes would never converge - the exact
   failure the merge policy exists to prevent. Both paths now `forceFill`, and
   the payload is first narrowed to the columns the table actually has, so an
   unknown remote key cannot reach the statement. The insert path uses
   `forceFill` on a new instance rather than `create()`, for the same reason,
   and still drops the origin's `id`.
3. `stampSyncMetadata()` ran on saves made by the applier, replacing the
   origin's HLC with a fresh local one. It now yields while
   `SyncLogger::isApplying()` is set, alongside capture.
4. Timestamps: the payload serialises dates as UTC, which is correct for
   transport, but assigning an ISO-8601 value makes Eloquent format it in UTC
   while this engine reads the column back in `app.timezone`
   (`Africa/Lagos`). Replay shifted every timestamp by the offset, so the log
   applied twice was not byte-identical - the precise failure spec section 13
   asks the test to catch. Dates are converted to this node's database format
   before assignment. Two nodes in different timezones each store their own
   wall-clock rendering of the same instant.

The test file ships the plan's two cases plus four regression tests, one per
defect above.

`SyncEntityRegistry` maps the fifteen tables that carry sync fields. The four
catalogue entries with no model there - `inventory_stocks`, `drink_stock`,
`settings`, `roles` - have no capture path either, so no change for them can
be produced today. The docblock records that an unknown key failing loudly is
the intended behaviour of a whitelist.

---

## Task 5: Build the two-node harness

The existing `TwoDbSyncTest` is:

```php
$db1 = DB::connection("sqlite");
$db2 = DB::connection("sqlite");
$this->assertNotNull($db1);
$this->assertNotNull($db2);
```

Both calls return the same `:memory:` handle. The test asserts that a connection
is not null, which proves nothing about two nodes. `SyncEngineTest` is similarly
thin: an HLC string round-trip and an accessor.

Spec §12 Phase 2 requires testing "as a library against two SQLite databases
simulating two disconnected nodes."

**Files:**
- Create: `tests/Support/TwoNodeSyncHarness.php`
- Rewrite: `tests/Feature/Sync/TwoDbSyncTest.php`, `tests/Feature/Sync/SyncEngineTest.php`
- Create: `tests/Feature/Sync/TwoNodeConvergenceTest.php`

**Interfaces:**
- Consumes: Tasks 2–4.
- Produces: `TwoNodeSyncHarness::make()`, `->migrateBoth()`, `->syncBothWays()`, `->snapshot(string $node)`, `->cleanup()`. Phase 4R reuses this for multi-process proof.

- [x] **Step 1: Build the harness on two real files**

Two `:memory:` connections are one handle. Use two temporary files and two named
connections registered at runtime.

```php
<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Two genuinely separate file-backed SQLite databases.
 *
 * Two `:memory:` connections are the same handle, which makes a two-node test
 * silently meaningless. These are real files on disk with separate migrations.
 */
class TwoNodeSyncHarness
{
    private function __construct(
        private readonly string $dir,
        private readonly string $pathA,
        private readonly string $pathB,
    ) {
    }

    public static function make(): self
    {
        $dir = sys_get_temp_dir() . '/hotelms-sync-' . bin2hex(random_bytes(6));

        if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Could not create harness directory [{$dir}].");
        }

        $a = $dir . '/node-a.sqlite';
        $b = $dir . '/node-b.sqlite';

        touch($a);
        touch($b);

        foreach (['node_a' => $a, 'node_b' => $b] as $name => $path) {
            Config::set('database.connections.' . $name, [
                'driver'   => 'sqlite',
                'database' => $path,
                'prefix'   => '',
                'foreign_key_constraints' => true,
            ]);
        }

        return new self($dir, $a, $b);
    }

    public function migrateBoth(): void
    {
        $original = DB::getDefaultConnection();

        try {
            foreach (['node_a', 'node_b'] as $name) {
                DB::setDefaultConnection($name);
                DB::purge($name);

                Artisan::call('migrate:fresh', ['--force' => true]);
            }
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    public function use(string $node): void
    {
        DB::setDefaultConnection($node === 'a' ? 'node_a' : 'node_b');
        DB::purge(DB::getDefaultConnection());
    }

    /**
     * Exchange change logs in both directions and apply them.
     *
     * In-process stand-in for the HTTP transport Phase 4R builds. Both nodes
     * are the same PHP process here, which is fine: what is being proved is
     * that merge converges, not that the network works.
     */
    public function syncBothWays(): void
    {
        $this->exchange('a', 'b');
        $this->exchange('b', 'a');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $node, string $table = 'inventory_items'): array
    {
        $this->use($node);

        $rows = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        // Sync metadata legitimately differs per node. Compare business data.
        foreach ($rows as $i => $row) {
            unset($rows[$i]['sync_hlc'], $rows[$i]['sync_origin_node_id']);
        }

        return $rows;
    }

    public function cleanup(): void
    {
        DB::purge('node_a');
        DB::purge('node_b');

        foreach ([$this->pathA, $this->pathB] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }
}
```

- [x] **Step 2: Add the exchange helper to the test class**

Exchange belongs in the test, not the harness, because it is the stand-in for
the transport that Phase 4R replaces.

```php
private function exchange(string $from, string $to): void
{
    $harness = $this->harness;
    $harness->use($from);

    $changes = App\Models\Sync\SyncChange::orderBy('sequence')->get()
        ->map(fn ($c) => [
            'table_name' => $c->table_name,
            'row_id'     => $c->row_id,
            'operation'  => $c->operation,
            'node_id'    => $c->node_id,
            'hlc'        => $c->hlc,
            'payload'    => $c->payload,
        ])
        ->all();

    $harness->use($to);

    foreach ($changes as $change) {
        app(App\Sync\SyncApplier::class)->apply(
            new App\Models\Sync\SyncChange($change)
        );
    }
}
```

An unsaved `SyncChange` works here because `apply()` reads only attributes. Once
Phase 4R adds real transport, this becomes a genuine HTTP payload.

- [x] **Step 3: Prove the two nodes are actually separate**

This is the assertion the current test is missing. If it fails, every other
two-node test is worthless.

```php
public function test_the_two_nodes_are_separate_databases(): void
{
    $this->harness->use('a');
    InventoryItem::create(['name' => 'Only on A', 'sku' => 'A-1', 'unit' => 'kg']);

    $this->harness->use('b');
    $this->assertSame(0, InventoryItem::count());
}
```

- [x] **Step 4: Rewrite `TwoDbSyncTest` to use the harness**

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

class TwoDbSyncTest extends TestCase
{
    private TwoNodeSyncHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = TwoNodeSyncHarness::make();
        $this->harness->migrateBoth();
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();

        parent::tearDown();
    }

    public function test_the_two_nodes_are_separate_databases(): void
    {
        $this->harness->use('a');
        InventoryItem::create(['name' => 'Only on A', 'sku' => 'A-1', 'unit' => 'kg']);

        $this->harness->use('b');
        $this->assertSame(0, InventoryItem::count());
    }

    public function test_a_row_written_on_one_node_reaches_the_other_after_sync(): void
    {
        config(['sync.enabled' => true, 'sync.node_id' => 'node-a']);

        $this->harness->use('a');
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->exchange('a', 'b');

        $this->harness->use('b');
        $this->assertSame(1, InventoryItem::count());
        $this->assertSame('Rice', InventoryItem::first()->name);
    }
}
```

- [x] **Step 5: Prove convergence**

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

class TwoNodeConvergenceTest extends TestCase
{
    use RefreshDatabase;

    private TwoNodeSyncHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sync.enabled' => true]);

        $this->harness = TwoNodeSyncHarness::make();
        $this->harness->migrateBoth();
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();

        parent::tearDown();
    }

    public function test_disconnected_writes_converge_after_bidirectional_sync(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);
        $first = InventoryItem::first();
        $first->update(['name' => 'Jollof Rice']);

        $this->harness->use('b');
        config(['sync.node_id' => 'node-b']);
        InventoryItem::create(['name' => 'Beans', 'sku' => 'B-1', 'unit' => 'kg']);

        $this->exchange('a', 'b');
        $this->exchange('b', 'a');

        $this->assertEqualsCanonicalizing(
            $this->harness->snapshot('a'),
            $this->harness->snapshot('b'),
        );
    }

    public function test_convergence_holds_regardless_of_exchange_order(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->harness->use('b');
        config(['sync.node_id' => 'node-b']);
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);
        InventoryItem::first()->update(['name' => 'Local Override']);

        $this->exchange('b', 'a');
        $this->exchange('a', 'b');

        $this->assertEqualsCanonicalizing(
            $this->harness->snapshot('a'),
            $this->harness->snapshot('b'),
        );
    }

    public function test_sync_is_idempotent_when_run_twice(): void
    {
        $this->harness->use('a');
        config(['sync.node_id' => 'node-a']);
        InventoryItem::create(['name' => 'Rice', 'sku' => 'R-1', 'unit' => 'kg']);

        $this->exchange('a', 'b');
        $afterFirst = $this->harness->snapshot('b');

        $this->exchange('a', 'b');
        $this->exchange('a', 'b');

        $this->assertSame($afterFirst, $this->harness->snapshot('b'));
    }
}
```

The second test is the one that actually proves merge determinism: both nodes
wrote the same SKU with different names, and the winner must be the same on both
regardless of who pushed first. That is spec §5.3's central claim.

- [x] **Step 6: Strengthen `SyncEngineTest`**

Keep the HLC round-trip but assert the properties that actually matter: monotonic
counter behaviour and a stable tie-break.

```php
public function test_hlc_round_trips(): void
{
    $hlc = Hlc::now('node1');

    $this->assertSame($hlc->nodeId, Hlc::fromString($hlc->toString())->nodeId);
}

public function test_hlc_is_strictly_increasing_within_a_node(): void
{
    $a = Hlc::now('node1');
    $b = Hlc::now('node1')->increment();

    $this->assertGreaterThan($a->toString(), $b->toString());
}

public function test_hlc_max_breaks_ties_by_node_id(): void
{
    $a = Hlc::fromString('001:0000000001:0000000000:aaa');
    $b = Hlc::fromString('001:0000000001:0000000000:bbb');

    $this->assertSame('bbb', Hlc::max($a, $b)->nodeId);
    $this->assertSame('bbb', Hlc::max($b, $a)->nodeId);
}
```

- [x] **Step 7: Run the sync suite**

```powershell
php artisan test --filter=Sync
```

Expected: every sync test passes, and `test_the_two_nodes_are_separate_databases`
confirms the harness is real.
Commit: `test: replace placeholder sync tests with a real two-node harness`

---

### Task 5 execution notes

**Live decisions (user-approved during execution):**

1. **Duplicate business keys are out of scope for this task.** The plan's
   `test_convergence_holds_regardless_of_exchange_order` wrote `R-1` on both
   nodes; on the second insert the `inventory_items.sku` unique index raised
   `UniqueConstraintViolationException` before any sync ran. Per user decision,
   duplicate-SKU convergence is recorded as a new open gap and this task proves
   determinism a different way (below).
2. **The merge policy was never wired.** `SyncMerger::merge()` had zero call
   sites; the applier force-filled by `sync_uid`, so two offline edits of one
   row *swapped* instead of converging (A ended "Edited on B", B ended "Edited
   on A" — proven with a temporary probe). Per user decision, Task 5 wires the
   catalogue policy into `SyncApplier` and persists blocked merges to
   `sync_conflicts` (new `reason` column, migration `2026_10_05_100026`),
   matching the `ConflictPolicy` contract.

**Resulting behaviour:** the applier now applies a change through the table's
policy. A `ConflictResult` stops the write and records an open conflict; a row
alive on both sides is merged last-write-wins (or the table's policy) instead
of clobbered. `ConflictPersistenceTest` proves a `paid` vs `refunded` payment
records `captured_then_refunded` on both nodes and neither side is written.

**Deviations from the plan's code:**

- `exchange()` moved onto the harness (public `TwoNodeSyncHarness::exchange`)
  instead of a private test helper: three test classes and the golden fixture
  need it. `syncBothWays()` still exists for symmetric use.
- `snapshot()` also unsets `id` — the integer primary key is node-local and
  differs on insert order.
- `RefreshDatabase` dropped from the convergence tests: the harness owns both
  databases entirely.
- `SyncEngineTest` fixtures followed the step's pseudocode but the colon format
  (`001:0000000001:0000000000:aaa`) does not parse; the real format is
  dash-separated `timestamp-counter-nodeId` (`1000000000001-0-aaa`).
- `TwoNodeConvergenceTest` replaced the unique-SKU test with **forced-clock
  determinism**: `seedDivergence()` writes the same sku on both nodes, edits
  each side, then rewrites both rows' HLC and their last change payload to
  known values. `test_the_higher_hlc_wins_in_either_exchange_order` asserts the
  newer side wins on both nodes in both push orders; a stale remote change
  loses. Plus `test_sync_is_idempotent_when_run_twice`.

**Bugs found by making convergence actually run (fixed in this task):**

- `Hlc::fromString()` threw on every production stamp: the default node id
  `node-a` contains a dash, so `1791418426582-0-node-a` exploded into four
  parts. Every merge silently degenerated to a null-HLC local win — remote
  updates were dropped and the merged `sync_hlc` was blanked. Parsing now
  accepts 2-part legacy stamps and 3-part stamps whose numeric counter splits
  off a dashed node id.
- `Syncable` logged every second write through one instance as `create`,
  because `wasRecentlyCreated` is set on insert and never cleared. The
  operation now comes from `creating`/`updating` listener flags.
- `IdempotencyTest::test_applying_a_change_overwrites_columns_the_model_does_not_fill`
  encoded blind-upsert semantics (payload always wins). Under merge, identical
  local and remote HLCs tie and the local row legitimately keeps its newer
  quantity. The test now forces a strictly newer remote HLC so the non-fillable
  `quantity` column still demonstrates the forceFill path.

**Commit:** `feat: converge offline edits through catalogue merge policies`
(Sync filter: 43 passed; full suite: 212 passed, 811 assertions).

---

## Task 6: Commit the golden fixture

Spec §13: "Golden synchronization fixture — a committed JSON scenario replayed on
every engine, so SQLite and Postgres cannot drift."

**Files:**
- Create: `tests/Fixtures/sync/golden-scenario.json`
- Create: `tests/Feature/Sync/GoldenFixtureTest.php`

**Interfaces:**
- Consumes: Tasks 3–5.
- Produces: a scenario file Phase 1R can also replay against Postgres.

- [x] **Step 1: Write the scenario**

```json
{
  "name": "two-node-mixed-writes",
  "description": "Both nodes create and update the same entity while disconnected. Both must converge on identical business state.",
  "nodes": ["node-a", "node-b"],
  "operations": [
    {
      "node": "node-a",
      "entity": "inventory_items",
      "action": "create",
      "row": { "id": 1, "name": "Rice", "sku": "INV-RICE-001", "unit": "kg" }
    },
    {
      "node": "node-b",
      "entity": "inventory_items",
      "action": "create",
      "row": { "id": 2, "name": "Beans", "sku": "INV-BEANS-001", "unit": "kg" }
    },
    {
      "node": "node-a",
      "entity": "inventory_items",
      "action": "update",
      "row": { "id": 1, "name": "Jollof Rice", "sku": "INV-RICE-001", "unit": "kg" }
    }
  ],
  "assert": {
    "converges": true,
    "expected_rows": [
      { "id": 1, "name": "Jollof Rice", "sku": "INV-RICE-001", "unit": "kg" },
      { "id": 2, "name": "Beans", "sku": "INV-BEANS-001", "unit": "kg" }
    ]
  }
}
```

- [x] **Step 2: Write the replay test**

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Tests\Support\TwoNodeSyncHarness;
use Tests\TestCase;

class GoldenFixtureTest extends TestCase
{
    public function test_the_golden_scenario_converges_on_every_engine(): void
    {
        $scenario = json_decode(
            (string) file_get_contents(__DIR__ . '/../../Fixtures/sync/golden-scenario.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $harness = TwoNodeSyncHarness::make();
        $harness->migrateBoth();

        try {
            foreach ($scenario['operations'] as $operation) {
                $harness->use($operation['node'] === 'node-a' ? 'a' : 'b');
                config(['sync.node_id' => $operation['node']]);

                $row = $operation['row'];

                if ($operation['action'] === 'create') {
                    InventoryItem::create($row);
                } else {
                    InventoryItem::findOrFail($row['id'])->update($row);
                }
            }

            foreach ($scenario['nodes'] as $index => $node) {
                $harness->use($node === 'node-a' ? 'a' : 'b');
                $this->exchange('a', 'b');
                $this->exchange('b', 'a');
            }

            $a = $harness->snapshot('a');
            $b = $harness->snapshot('b');

            $this->assertEqualsCanonicalizing($a, $b);

            $expected = $scenario['assert']['expected_rows'];

            foreach ($expected as $row) {
                $this->assertDatabaseHas('inventory_items', [
                    'id'    => $row['id'],
                    'name'  => $row['name'],
                    'sku'   => $row['sku'],
                    'unit'  => $row['unit'],
                ]);
            }
        } finally {
            $harness->cleanup();
        }
    }
}
```

Keep the `exchange` helper in a shared trait rather than duplicating it across
three test classes. Extract it to `tests/Support/ExchangesChangesBetweenNodes.php`
in this task.

- [x] **Step 3: Verify the fixture replays identically twice**

```powershell
php artisan test --filter=GoldenFixtureTest
php artisan test --filter=GoldenFixtureTest
```

Both runs must pass. A fixture that passes once is not a regression guard.
Commit: `test: add golden sync fixture replayed on every engine`

### Task 6 execution notes

- The fixture is the plan's scenario verbatim: two creates (one per node) and
  one update on node-a, then a bidirectional exchange; the two nodes must
  converge to `Jollof Rice / INV-RICE-001` and `Beans / INV-BEANS-001`.
- The `id` values in the fixture are illustrative only. `id` is node-local and
  not fillable on `InventoryItem`, so the replay drops it and locates update
  targets by `sku` (the fixture's stable business key). The plan's sample used
  `findOrFail($row['id'])` and asserted `id == 1` via `assertDatabaseHas`,
  which only holds on the node that created the row; the node that inserts from
  a sync payload may assign a different integer.
- The plan's shared-`exchange`-trait extraction is unnecessary: `exchange`
  already lives on `TwoNodeSyncHarness`, shared by every sync test class and
  by this test. Phase 4R replaces one harness method rather than one trait.
- The assertion is stronger than the plan's `assertEqualsCanonicalizing`:
  `assertSame($harness->snapshot('a'), $harness->snapshot('b'))` proves the two
  nodes end byte-identical on every business column, `sync_uid`, and both
  timestamps, then `assertDatabaseHas` checks the expected rows per node.
- `GoldenFixtureTest` runs twice in Step 3; both runs passed (5 assertions
  each). Full suite after adding it: 213 passed, 816 assertions.

**Commit:** `test: add golden sync fixture replayed on every engine`

---

## Part B exit criteria

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase2r_b.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
php artisan test
```

- `migrate:fresh` reports `172 DONE, 0 FAIL`.
- The full suite passes with zero failures and zero skips.
- `test_the_two_nodes_are_separate_databases` passes, proving the harness is two
  real databases.
- Convergence and order-independence tests pass.
- Applying a remote change appends no new change row.
- Disabling `sync.enabled` produces no change rows and no HLC stamps.

Verify the dead columns are now alive:

```powershell
php artisan tinker --execute="
config(['sync.enabled' => true, 'sync.node_id' => 'verify-node']);
\$i = App\Models\InventoryItem::create(['name' => 'X', 'sku' => 'X-1', 'unit' => 'kg']);
echo 'sync_hlc: ' . var_export(\$i->fresh()->sync_hlc, true) . PHP_EOL;
echo 'changes:  ' . App\Models\Sync\SyncChange::count() . PHP_EOL;
"
```

Expected: a non-null HLC string and `changes: 1`. Before this part both were null
and zero.

Phase 2R is then complete. Next: the
[Ziggy runtime route manifest](2026-10-05-ziggy-runtime-route-manifest.md),
which the spec calls a prerequisite for serving the application from any node
other than the cloud.
