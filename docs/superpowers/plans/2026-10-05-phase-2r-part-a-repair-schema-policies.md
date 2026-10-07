# Phase 2R — Repair and Complete the Sync Engine (Part A: repair, schema, policies)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Continue in:** `2026-10-05-phase-2r-part-b-capture-and-convergence.md`

**Goal:** Make the sync engine real. Repair the nine unloadable files, align the four sync tables with spec §5.2 and §5.4, and implement the five-class policy catalogue so merge is a genuine pure function.

**Architecture:** One abstract policy base, one catalogue mapping entity type to policy, one pure merge dispatcher. Merge takes `(entityType, localRow, remoteRow)` and returns either a merged row or a `ConflictResult`. Part B adds change-log capture, the applier, and the two-node convergence proof.

**Tech Stack:** Laravel 12, PHP 8.2+, SQLite 3 (two file-backed databases), MySQL, PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md` — §5.1 (HLC), §5.2 (append-only change log), §5.3 (pure merge), §5.4 (schema additions), §6 (policy catalogue), §13 (testing strategy).

**Closes:** G1, G3, G4, G6, G7, G8, G9, G10. (G2, G5, G11, G12, G43 close in Part B.)

---

## Global Constraints

- **Repair, do not redesign.** `app/Sync/Hlc.php` is correct and tested. Do not modify it. Read it and use it.
- **Merge must stay pure.** No database access, no clock reads, no randomness inside `SyncMerger` or any policy. Same inputs, same output, on any node. This is spec §5.3 and it is not negotiable.
- **Never rewrite an applied migration.** Schema changes ship as new migrations.
- **Write files with real newlines.** Nine files in this area were corrupted by literal `\n` byte sequences. Verify with a byte check, never `php -l` alone — `php -l` reports the corrupt files as valid because PHP parses them as inline HTML.
- Commit after every task. Focused commits. Never batch unrelated changes.
- Do not modify `.env`. Use inline environment variables for all database work.

---

## File Structure

**Rewritten (all nine currently unloadable, all with zero real newlines):**
- `app/Sync/Policies/SyncPolicy.php` — abstract base.
- `app/Sync/Policies/MergePolicy.php` — Class 1, immutable append.
- `app/Sync/Policies/LastWriteWinsPolicy.php` — Class 2, row-level (see Task 3 Step 4 for the spec amendment).
- `app/Sync/Policies/AdditivePolicy.php` — Class 3, derived balances.
- `app/Sync/Policies/ConflictPolicy.php` — Class 4, hard conflicts.
- `app/Sync/Policies/IdempotentPolicy.php` — replay is a primary-key no-op.
- `app/Models/Sync/SyncNode.php`
- `app/Models/Sync/SyncCursor.php`
- `app/Models/Sync/SyncConflict.php`

**Created:**
- `app/Sync/SyncScope.php` — the §6 catalogue as data. One place, no scattered conditionals.
- `app/Sync/ConflictResult.php` — value object for a blocked Class 4 merge.
- `database/migrations/2026_10_05_100020_add_property_id_and_credential_to_sync_nodes.php`
- `database/migrations/2026_10_05_100021_add_sequence_and_payload_to_sync_changes.php`
- `database/migrations/2026_10_05_100022_widen_sync_row_id_for_ulid_identity.php`
- `database/migrations/2026_10_05_100023_replace_cursor_hlc_with_last_applied_change_id.php`
- `database/migrations/2026_10_05_100024_add_sync_uid_to_synced_domain_tables.php`
- `database/migrations/2026_10_05_100025_create_sync_sequences_table.php`
- `tests/Feature/Sync/PolicyCatalogueTest.php`

**Modified:**
- `app/Sync/SyncMerger.php` — replace `array_merge` with catalogue dispatch.

**Responsibility note:** this part makes the policies real and loadable. It touches no domain table, per spec §12 Phase 2.

---

## Task 1: Repair the nine corrupt files

Nothing else works until this is done. `php -l` reports these files as
syntactically valid, which is exactly why the defect survived a suite run.
`SyncNode.php` even contains a syntactically impossible `protected \ = ...`
declaration.

**Files:** All nine files in File Structure.

**Interfaces:**
- Consumes: nothing.
- Produces: a loadable `SyncPolicy` abstract base with `merge(array $local, array $remote): array|ConflictResult`. Part B and every later phase depend on this.

- [x] **Step 1: Confirm the corruption with a byte check**

```powershell
python -c "import glob; [print(p, 'newlines=', open(p,'rb').read().count(b'\x0a'), 'literal=', open(p,'rb').read().count(b'\x5cn')) for p in sorted(glob.glob('app/Sync/Policies/*.php') + glob.glob('app/Models/Sync/*.php'))]"
```

Expected: `app/Sync/Policies/*.php` and `SyncNode.php`, `SyncCursor.php`,
`SyncConflict.php` all report `newlines=0`. `SyncChange.php`, `Syncable.php`,
`SyncMerger.php` and `Hlc.php` report a non-zero count — those four are intact.

- [x] **Step 2: Confirm they cannot autoload**

```powershell
php artisan tinker --execute="var_dump(class_exists('App\Sync\Policies\SyncPolicy')); var_dump(class_exists('App\Models\Sync\SyncNode')); var_dump(class_exists('App\Sync\Hlc'));"
```

Expected: `bool(false)`, `bool(false)`, `bool(true)`.

- [x] **Step 3: Write the abstract base with real newlines**

```php
<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

abstract class SyncPolicy
{
    /**
     * Merge a local row with a remote row for the same entity.
     *
     * Pure by contract: identical inputs must produce identical output on
     * every node. No database access, no clock reads, no randomness.
     *
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>|ConflictResult
     */
    abstract public function merge(array $local, array $remote): array|ConflictResult;
}
```

The return union is the important change. The existing placeholder signature is
`merge(array $local, array $remote, array $policy = [])` returning a hard `array`,
which is structurally incapable of representing a Class 4 conflict.

- [x] **Step 4: Write the three sync models with real newlines**

`SyncNode` needs a working primary key. The corrupt version derived it as
`strtolower(str_replace('Sync', 'sync_', 'SyncNode'))`, which yields
`sync_node` — a table that does not exist. Use the actual table and a fillable
list matching the schema.

```php
<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;

class SyncNode extends Model
{
    protected $table = 'sync_nodes';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'last_seen_at' => 'datetime',
    ];
}
```

Apply the same treatment to `SyncCursor` (table `sync_cursors`, casts
`last_synced_at` to datetime) and `SyncConflict` (table `sync_conflicts`, casts
`local_change`, `remote_change` and `resolution` to array, `resolved_at` to
datetime).

- [x] **Step 5: Verify newlines and autoload for all nine**

```powershell
python -c "import glob; [print(p, 'newlines=', open(p,'rb').read().count(b'\x0a')) for p in sorted(glob.glob('app/Sync/Policies/*.php') + glob.glob('app/Models/Sync/*.php'))]"
```

Expected: every file reports a non-zero newline count.

```powershell
php artisan tinker --execute="
foreach ([
 'App\Sync\Policies\SyncPolicy','App\Sync\Policies\MergePolicy',
 'App\Sync\Policies\LastWriteWinsPolicy','App\Sync\Policies\AdditivePolicy',
 'App\Sync\Policies\ConflictPolicy','App\Sync\Policies\IdempotentPolicy',
 'App\Models\Sync\SyncNode','App\Models\Sync\SyncCursor','App\Models\Sync\SyncConflict',
] as \$c) { echo \$c . ' => ' . (class_exists(\$c) ? 'ok' : 'MISSING') . PHP_EOL; }
"
```

Expected: nine `ok` lines. Commit: `fix: repair nine sync files that could not autoload`

---

## Task 2: Align the sync tables with spec §5.2 and §5.4

The four tables do not match the spec. `sync_cursors` tracks an HLC string where
the spec requires a change-id cursor. `sync_changes` has no per-node sequence
and stores diffs where §5.2 requires full snapshots. `row_id` is an unsigned
integer, which cannot hold the ULID the spec requires for row identity.

**Files:** the six new migrations in File Structure.

**Interfaces:**
- Consumes: Task 1.
- Produces: `sync_nodes` with `property_id` and `credential`; `sync_changes` with `sequence`, `payload` and a string-capable `row_id`; `sync_cursors` with `last_applied_change_id`; a `sync_uid` ULID column on every synced domain table; a `sync_sequences` counter table.

- [x] **Step 1: Add `property_id` and `credential` to `sync_nodes`**

Spec §4.4 keys the registry by `(cloud, property)` and §5.4 lists a credential
column. Neither exists in `2026_10_04_000001_create_sync_nodes_table.php`.

```php
$table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
$table->string('credential')->nullable();
$table->index(['property_id', 'type']);
```

Store a hashed credential, never a plaintext shared secret. This is the LAN and
cloud authentication surface, and Phase 4R authenticates against it.

- [x] **Step 2: Add per-node sequence and full-snapshot payload to `sync_changes`**

Spec §5.4 requires "per-node monotonic sequence". Spec §5.2 requires the full
row snapshot, and explains why: "snapshots eliminate an entire class of bug."

```php
$table->unsignedBigInteger('sequence')->default(0);
$table->longText('payload')->nullable();
$table->unique(['node_id', 'sequence']);
```

Keep `old_values` and `new_values` in place. They cost nothing and remain useful
for audit. `payload` becomes the authoritative full snapshot that Part B writes
and the applier reads.

- [x] **Step 3: Widen `row_id` and add `sync_uid` to every synced domain table**

Spec §5.4: "Every row retains the ULID minted at its origin node, so replaying a
change is a primary-key no-op and application is idempotent." The current
`unsignedBigInteger('row_id')` cannot hold a 26-character ULID.

**Widening `row_id` alone is not sufficient, and this is the most important step in
this task.** Every synced domain table has an auto-increment integer primary
key: `pos_dockets.id`, `inventory_items.id`, `bookings.id`. A ULID stored in
`sync_changes.row_id` has nothing to point at. An applier that looks up
`PosDocket::find('01HQ...')` returns null on every engine, and every sync change
fails to apply.

Converting domain primary keys to ULIDs is not an option: 165 migrations, dozens
of foreign keys, existing production data, and spec §10 requires synchronisation
to be purely additive.

The additive solution is a separate ULID column:

```php
// on sync_changes and sync_conflicts
$table->string('row_id')->change();          // was unsignedBigInteger
$table->index('row_id');
```

```php
// one migration over the 15 tables that already received sync fields
foreach (self::SYNCED_TABLES as $table) {
    Schema::table($table, function (Blueprint $blueprint) use ($table) {
        $blueprint->string('sync_uid', 26)->nullable()->unique();
    });
}
```

`sync_changes.row_id` then references `<domain table>.sync_uid`, never `.id`.
The existing integer `id` stays the primary key and stays what every existing
query, foreign key and report uses — so nothing else in the application changes.

Backfill `sync_uid` for existing rows in the same migration, minting one per row.
Without the backfill, every pre-existing row is unaddressable by sync and would be
re-created as a duplicate on first apply.

`$table->string('row_id')->change()` requires a table rebuild rather than an
in-place change on SQLite. Laravel handles the change directly on MySQL and
PostgreSQL; for SQLite, drop and recreate inside the same migration. Both tables
hold no production data yet, so recreation is safe. Note this in the migration's
docblock.

- [x] **Step 4: Add the per-node sequence counter table**

Part B allocates a per-node sequence. Reading `max(sequence) + 1` is a race: two
concurrent writes on one node read the same maximum, both mint the same value, and
the unique index turns a lost write into a failed sale. Allocation needs a locked
counter row.

```php
Schema::create('sync_sequences', function (Blueprint $table) {
    $table->string('node_id', 64)->primary();
    $table->unsignedBigInteger('sequence')->default(0);
    $table->timestamps();
});
```

Part B's `SyncLogger::nextSequence()` locks this row with `lockForUpdate()`.

- [x] **Step 5: Replace `cursor_hlc` with `last_applied_change_id`**

Spec §5.4 defines a cursor as `(peer_node_id, last_applied_change_id)`. An HLC
string is not that and cannot express "everything up to change 412" precisely.

```php
$table->unsignedBigInteger('last_applied_change_id')->nullable();
$table->dropColumn('cursor_hlc');
```

The existing `unique(['node_id', 'target_node_id'])` already matches the
`(peer, cursor)` pairing, so it stays.

- [x] **Step 6: Verify migrations and the full gate**

```powershell
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE="$env:TEMP\hotelms_phase2r.sqlite"
Remove-Item $env:DB_DATABASE -ErrorAction SilentlyContinue
New-Item -ItemType File -Path $env:DB_DATABASE -Force | Out-Null
php artisan migrate:fresh --force
php artisan test
```

Expected: `171 DONE, 0 FAIL` (165 plus 6; Step 3 ships two migrations), suite unchanged from the start of the phase.
Commit: `feat: align sync tables with spec 5.2 and 5.4`

---

## Task 3: Build the policy catalogue

This is the correctness core. Spec §6 gives five classes and one classification
principle: **does the row represent something that happened, or something that is
currently true?**

**Files:**
- Create `app/Sync/SyncScope.php`, `app/Sync/ConflictResult.php`.
- Rewrite the five policy classes.
- Create `tests/Feature/Sync/PolicyCatalogueTest.php`.

**Interfaces:**
- Consumes: Task 1 (`SyncPolicy` base), `app/Sync/Hlc.php`.
- Produces: `SyncScope::policyFor(string $entityKey): SyncPolicy`, `SyncScope::has(string $entityKey): bool` and `SyncScope::mayEmit(string $entityKey): bool`.
  - Part B `SyncLogger` consumes `has()` and `mayEmit()`.
  - Phase 4R consumes `keys()`.
  - Phase 4S fills in `config('sync_scope.read_only')`, which `mayEmit()` reads.

- [x] **Step 1: Define the catalogue as data**

```php
<?php

namespace App\Sync;

use App\Sync\Policies\AdditivePolicy;
use App\Sync\Policies\ConflictPolicy;
use App\Sync\Policies\LastWriteWinsPolicy;
use App\Sync\Policies\MergePolicy;

final class SyncScope
{
    /**
     * Spec section 6. Classification principle: does the row represent
     * something that happened, or something that is currently true?
     *
     * @var array<string, class-string<\App\Sync\Policies\SyncPolicy>>
     */
    private const CATALOGUE = [
        // Class 1 - immutable facts. Append-only, never merged.
        'pos_dockets' => MergePolicy::class,
        'pos_docket_items' => MergePolicy::class,
        'pos_docket_payments' => MergePolicy::class,
        'pos_print_logs' => MergePolicy::class,
        'inventory_movements' => MergePolicy::class,
        'drink_stock_movements' => MergePolicy::class,
        'payments' => MergePolicy::class,
        'audit_logs' => MergePolicy::class,

        // Class 2 - mutable reference data. Row-level last-write-wins.
        'inventory_items' => LastWriteWinsPolicy::class,
        'inventory_locations' => LastWriteWinsPolicy::class,
        'room_types' => LastWriteWinsPolicy::class,
        'rooms' => LastWriteWinsPolicy::class,
        'staff_profiles' => LastWriteWinsPolicy::class,

        // Class 3 - derived balances. Never authoritative; recomputed.
        'inventory_stocks' => AdditivePolicy::class,
        'drink_stock' => AdditivePolicy::class,

        // Class 4 - hard conflicts. Blocked, surfaced to a human.
        'bookings' => ConflictPolicy::class,
        'pos_sessions' => ConflictPolicy::class,

        // Class 5 - cloud-authoritative. Pushed down, never contested.
        'settings' => MergePolicy::class,
        'roles' => MergePolicy::class,
    ];

    public static function policyFor(string $entityKey): SyncPolicy
    {
        $class = self::CATALOGUE[$entityKey] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("No sync policy registered for [{$entityKey}].");
        }

        return new $class();
    }

    public static function has(string $entityKey): bool
    {
        return isset(self::CATALOGUE[$entityKey]);
    }

    /**
     * May this node emit a change for this table?
     *
     * Some tables are reference data that only the author node writes. A
     * terminal that emits a Class 2 change for one of them would fight the
     * cloud over live configuration, and last-write-wins means it can win with
     * a stale clock and leave no conflict record.
     *
     * `config('sync_scope.read_only')` does not exist yet — Phase 4S creates
     * it. The default is an empty list, so this is inert until 4S supplies the
     * data. The mechanism ships here; the contents ship there.
     */
    public static function mayEmit(string $entityKey): bool
    {
        if (config('sync.node_type') !== 'terminal') {
            return true;
        }

        $readOnly = (array) config('sync_scope.read_only', []);

        return ! in_array($entityKey, $readOnly, true);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }
}
```

`bookings` and `pos_sessions` are Class 4. The spec names a `cash_shifts` table
for the shift conflict, but no such table exists — the real tables are
`pos_sessions` and `pos_devices`. Phase 0R corrects the spec text.

Throwing on an unregistered key is deliberate. An unmapped entity means nobody
decided its conflict semantics, and silently picking a policy is how divergent
state gets produced.

- [x] **Step 2: Implement the `ConflictResult` value object**

```php
<?php

namespace App\Sync;

final class ConflictResult
{
    public function __construct(
        public readonly array $local,
        public readonly ?array $remote,
        public readonly string $reason = '',
    ) {
    }

    public function isBlocked(): bool
    {
        return true;
    }
}
```

- [x] **Step 3: Implement Class 1 — immutable append**

Two nodes creating rows of the same class both survive, so no conflict is
possible. When both sides carry the same row key, the newer HLC wins; otherwise
both survive.

```php
<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;
use App\Sync\Hlc;

class MergePolicy extends SyncPolicy
{
    /**
     * Class 1: immutable facts.
     *
     * Same row key means it is the same fact seen twice, so the newer HLC wins.
     * Different row keys mean two genuinely distinct facts -- two dockets, two
     * payments -- and both must survive. Returning one of them here would
     * silently discard a sale.
     */
    public function merge(array $local, array $remote): array|ConflictResult
    {
        if (($local['sync_row_key'] ?? null) !== ($remote['sync_row_key'] ?? null)) {
            return $this->union($local, $remote);
        }

        return $this->newest($local, $remote);
    }

    private function newest(array $local, array $remote): array
    {
        $localHlc = $this->hlcOf($local);
        $remoteHlc = $this->hlcOf($remote);

        if ($remoteHlc === null) {
            return $local;
        }

        if ($localHlc === null || $remoteHlc->greaterThan($localHlc)) {
            return $remote;
        }

        // Equal HLC: the physical clock and counter matched. Node id keeps the
        // answer identical on every node that compares these two rows.
        return (string) ($remote['sync_origin_node_id'] ?? '') > (string) ($local['sync_origin_node_id'] ?? '')
            ? $remote
            : $local;
    }

    /**
     * Two distinct rows that must both be kept.
     *
     * This is why the merge signature returns array|ConflictResult rather than
     * always an array: a Class 1 union can produce a *list* of rows. The caller
     * in Task 4 must handle a returned list by writing every element, not by
     * updating one model. Document that in the return type when implementing.
     */
    private function union(array $local, array $remote): array
    {
        return [$local, $remote];
    }

    private function hlcOf(array $row): ?Hlc
    {
        $raw = trim((string) ($row['sync_hlc'] ?? ''));

        return $raw === '' ? null : Hlc::fromString($raw);
    }
}
```

`Hlc::max` already applies the node-id tie-break, so this comparison is
deterministic across nodes. That determinism is what makes convergence hold.

- [x] **Step 4: Implement Class 2 — row-level last-write-wins**

**Decision made: Option A.** Ship row-level LWW and amend §6 to say "row-level".
This is recorded so a later reader does not reopen it.

The reasoning, for the record. Spec §6 Class 2 said "Field-level
last-write-wins", but `sync_fields` adds one `sync_hlc` column per **row**, so
that table shape can only express **row-level** LWW. True field-level LWW needs a
`sync_field_hlc JSON` column mapping field name to HLC, maintained on every
write. Option A was chosen because the concurrent-edit case is narrow: two
terminals editing two *different fields of the same row while both are offline*
is the only scenario that loses data, and menu and inventory edits are
authored centrally and pushed down, not co-authored offline.

**The spec amendment is part of this task, not a follow-up.** Committing
row-level LWW while §6 still says field-level leaves a document that promises
semantics the engine does not implement, and the next person to read §6 will
build on a false assumption.

Amend `docs/superpowers/specs/2026-10-04-desktop-lan-offline-sync-design.md` §6
Class 2 to read:

> Mutable reference data. **Row-level** last-write-wins: the row with the higher
> HLC replaces the other. Concurrent edits to different fields of the same row
> resolve in favour of the later write; this is a known and accepted limitation.

Add to that section:

> Not implemented: field-level last-write-wins. It would require a per-field HLC
> map on every Class 2 row. If a lost edit is ever observed in practice, add
> `sync_field_hlc JSON` and change the policy.

The accepted risk, stated plainly: one terminal changing `menu_items.price`
discards another terminal's simultaneous change to `menu_items.description`.
Phase 4S narrows the Class 2 table set to the tables that are genuinely
co-authored; a narrower set makes this less likely, not impossible.

The sample below is therefore row-level LWW, and it is final for this phase.

```php
<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;
use App\Sync\Hlc;

/**
 * Class 2: row-level last-write-wins.
 *
 * The newest sync_hlc wins; sync_origin_node_id breaks a tie. Never surfaced to
 * a human. This is row-level, not the field-level the spec originally described,
 * because one sync_hlc per row cannot express per-field write times. See the
 * plan's Step 4 note.
 */
class LastWriteWinsPolicy extends SyncPolicy
{
    private const META = ['sync_hlc', 'sync_origin_node_id', 'sync_deleted_at', 'sync_row_key'];

    public function merge(array $local, array $remote): array|ConflictResult
    {
        $localHlc = $this->hlcOf($local);
        $remoteHlc = $this->hlcOf($remote);

        // A row that has never synced has no HLC and loses to anything that has.
        $remoteWins = $remoteHlc !== null
            && ($localHlc === null || $this->newer($remoteHlc, $localHlc, $local, $remote));

        $winner = $remoteWins ? $remote : $local;

        // The loser contributes only fields the winner does not have, so a
        // partially-populated remote row cannot erase local data by omission.
        $merged = $winner;

        foreach (($remoteWins ? $local : $remote) as $field => $value) {
            if (in_array($field, self::META, true)) {
                continue;
            }

            if (! array_key_exists($field, $merged)) {
                $merged[$field] = $value;
            }
        }

        $merged['sync_hlc'] = Hlc::max($localHlc, $remoteHlc)?->toString();
        $merged['sync_origin_node_id'] = $remoteWins
            ? ($remote['sync_origin_node_id'] ?? null)
            : ($local['sync_origin_node_id'] ?? null);

        return $merged;
    }

    private function hlcOf(array $row): ?Hlc
    {
        $raw = trim((string) ($row['sync_hlc'] ?? ''));

        // An empty or unparseable HLC is not a crash and not a win. Treat the row
        // as never-synced so it loses to any row that has a real timestamp.
        return $raw === '' ? null : Hlc::fromString($raw);
    }

    private function newer(Hlc $candidate, Hlc $incumbent, array $candidateRow, array $incumbentRow): bool
    {
        $byHlc = Hlc::max($candidate, $incumbent)->toString() === $candidate->toString();

        if ($byHlc !== ($incumbent->toString() === $candidate->toString())) {
            return $byHlc;
        }

        // Identical HLC: the physical clock and counter matched, so fall back to
        // node id for a deterministic, identical answer on every node.
        return (string) ($candidateRow['sync_origin_node_id'] ?? '')
            > (string) ($incumbentRow['sync_origin_node_id'] ?? '');
    }
}
```

Two things this sample gets right that a naive version does not:

- **An empty or absent `sync_hlc` loses**, and does not throw. A row created by a
  migration rather than by an application write has no HLC; it must not crash the
  merge or win it.
- **The loser cannot erase the winner by omission.** Only fields absent from the
  winning row are taken from the loser.

The tie-break on `sync_origin_node_id` is what makes the function *pure* in the
sense spec §5.3 requires: two nodes comparing the same two rows reach the same
answer without coordinating.

- [x] **Step 5: Implement Class 3 — derived balances**

Class 3 values are caches of a sum over Class 1 movements. They are never
exchanged as authoritative, so this policy exists mainly to make the "never
trust a remote balance" rule explicit in code rather than in a comment. Phase
4R proves the recomputed sum matches a rebuild.

```php
<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class AdditivePolicy extends SyncPolicy
{
    public function merge(array $local, array $remote): array|ConflictResult
    {
        // A derived balance has no independent truth. The local value is
        // discarded in favour of whatever the Class 1 ledger implies, which
        // SyncLogger recomputes at apply time. Carrying the remote balance
        // forward would import projection drift.
        $merged = $local;

        unset($merged['quantity'], $merged['total_stock']);

        return $merged;
    }
}
```

- [x] **Step 6: Implement Class 4 — block and surface**

Reuse the overlap detection that already exists in
`app/Services/RoomAvailabilityService.php:229-233` rather than writing new
date-comparison logic.

```php
<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class ConflictPolicy extends SyncPolicy
{
    public function merge(array $local, array $remote): array|ConflictResult
    {
        if ($this->overlaps($local, $remote)) {
            return new ConflictResult($local, $remote, 'overlapping_reservation');
        }

        return $local;
    }

    private function overlaps(array $local, array $remote): bool
    {
        if (($local['sync_row_key'] ?? null) === ($remote['sync_row_key'] ?? null)) {
            return false;
        }

        $localIn  = (string) ($local['check_in'] ?? '');
        $localOut = (string) ($local['check_out'] ?? '');
        $remoteIn  = (string) ($remote['check_in'] ?? '');
        $remoteOut = (string) ($remote['check_out'] ?? '');

        if ($localIn === '' || $localOut === '' || $remoteIn === '' || $remoteOut === '') {
            return false;
        }

        return $localIn < $remoteOut && $remoteIn < $localOut;
    }
}
```

Cover the other two Class 4 cases in the test: two `pos_sessions` claiming one
`pos_device_id`, and a payment captured on one node and refunded on another.

- [x] **Step 7: Implement Class 5 and the idempotent replay helper**

`IdempotentPolicy` makes the §5.4 replay guarantee explicit: applying a change
whose `sync_row_key` already exists locally is a no-op. Phase 4R's pull path
depends on this rather than re-deriving the check.

```php
<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class IdempotentPolicy extends SyncPolicy
{
    public function merge(array $local, array $remote): array|ConflictResult
    {
        if (($local['sync_row_key'] ?? null) === ($remote['sync_row_key'] ?? null)) {
            return $local;
        }

        return $remote;
    }
}
```

- [x] **Step 8: Write the policy catalogue unit test**

Spec §13: "Policy catalogue unit tests — explicit cases for each of the five
classes, including the three-term case where Class 4 must block."

```php
<?php

namespace Tests\Feature\Sync;

use App\Sync\ConflictResult;
use App\Sync\SyncScope;
use Tests\TestCase;

class PolicyCatalogueTest extends TestCase
{
    public function test_class1_keeps_both_rows_when_keys_differ(): void
    {
        $local  = ['sync_row_key' => 'a', 'amount' => 10, 'sync_hlc' => '001:0000000001:0000000000:n1'];
        $remote = ['sync_row_key' => 'b', 'amount' => 20, 'sync_hlc' => '001:0000000001:0000000000:n2'];

        $merged = SyncScope::policyFor('pos_dockets')->merge($local, $remote);

        $this->assertIsArray($merged);
        $this->assertSame(10, $merged['amount']);
        $this->assertSame(20, $merged['b_amount'] ?? $remote['amount']);
    }

    public function test_class2_newer_hlc_wins_per_field(): void
    {
        $local  = ['name' => 'Old', 'sync_hlc' => '001:0000000001:0000000000:n1'];
        $remote = ['name' => 'New', 'sync_hlc' => '001:0000000002:0000000000:n1'];

        $merged = SyncScope::policyFor('rooms')->merge($local, $remote);

        $this->assertSame('New', $merged['name']);
    }

    public function test_class2_node_id_breaks_hlc_ties_deterministically(): void
    {
        $local  = ['name' => 'L', 'sync_hlc' => '001:0000000001:0000000000:aaa'];
        $remote = ['name' => 'R', 'sync_hlc' => '001:0000000001:0000000000:bbb'];

        $first  = SyncScope::policyFor('rooms')->merge($local, $remote);
        $second = SyncScope::policyFor('rooms')->merge($remote, $local);

        $this->assertSame($first['name'], $second['name']);
    }

    public function test_class3_drops_the_remote_balance(): void
    {
        $local  = ['quantity' => 5, 'sync_hlc' => '001:0000000001:0000000000:n1'];
        $remote = ['quantity' => 99, 'sync_hlc' => '001:0000000002:0000000000:n1'];

        $merged = SyncScope::policyFor('inventory_stocks')->merge($local, $remote);

        $this->assertArrayNotHasKey('quantity', $merged);
    }

    public function test_class4_blocks_on_overlapping_bookings(): void
    {
        $local  = ['sync_row_key' => 'a', 'check_in' => '2026-11-01', 'check_out' => '2026-11-05', 'sync_hlc' => '001:0000000001:0000000000:n1'];
        $remote = ['sync_row_key' => 'b', 'check_in' => '2026-11-03', 'check_out' => '2026-11-07', 'sync_hlc' => '001:0000000001:0000000000:n2'];

        $result = SyncScope::policyFor('bookings')->merge($local, $remote);

        $this->assertInstanceOf(ConflictResult::class, $result);
        $this->assertSame('overlapping_reservation', $result->reason);
    }

    public function test_class4_allows_adjacent_bookings_that_do_not_overlap(): void
    {
        $local  = ['sync_row_key' => 'a', 'check_in' => '2026-11-01', 'check_out' => '2026-11-05', 'sync_hlc' => '001:0000000001:0000000000:n1'];
        $remote = ['sync_row_key' => 'b', 'check_in' => '2026-11-05', 'check_out' => '2026-11-08', 'sync_hlc' => '001:0000000001:0000000000:n2'];

        $this->assertIsArray(SyncScope::policyFor('bookings')->merge($local, $remote));
    }

    public function test_unregistered_entity_key_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SyncScope::policyFor('not_a_syncable_table')->merge([], []);
    }
}
```

Adjust the Class 1 assertion to match the `union()` implementation you write;
the point of the test is that both rows survive.

- [x] **Step 9: Run the catalogue test**

```powershell
php artisan test --filter=PolicyCatalogueTest
```

Expected: all cases pass. Commit: `feat: implement the five-class sync policy catalogue`

### Deviations recorded during execution

Five things in this task did not match the sample code, and each was resolved
rather than copied:

1. **`payments` moved from Class 1 to Class 4.** Spec §6 listed it in both
   Class 1 ("once written, never edited") and Class 4 ("a payment captured on
   one node and refunded on another"). The application mutates
   `payments.status` and `payments.refunded_at` in place on refund
   (`app/Services/FlutterwaveService.php:325`), so Class 1's premise was already
   false. Decided in favour of Class 4: money divergences are surfaced, never
   auto-resolved. The catalogue, `ConflictPolicy` and §6 now all agree.

2. **`ConflictPolicy` needed to know which entity it was holding.** The sample
   only ever did booking date-overlap detection, so `pos_sessions` — which has
   no `check_in`/`check_out` — was mapped to Class 4 but could never fire.
   `SyncPolicy` gained an optional `entityKey` constructor parameter and
   `SyncScope::policyFor()` passes it. `ConflictPolicy` dispatches on it.

3. **Two Class 4 rules were missing entirely.** The sample's Step 6 note asked
   the *test* to cover a device double-claim and a capture-against-refund, but
   supplied no code for either. Both rules are now implemented:
   `device_already_in_use` (same `device_id`, intersecting `[opened_at,
   closed_at)` intervals, null `closed_at` meaning still open) and
   `captured_then_refunded` (same row, both sides terminal, statuses pointing
   opposite ways). The status vocabularies were taken from the literals the
   application actually writes, not invented.

4. **The sample's column name was wrong.** The plan says `pos_device_id`; the
   column is `device_id`, a foreign Ulid to `pos_devices`.

5. **`ConflictPolicy::merge()` discarded the remote row.** It returned `$local`
   whenever no conflict was found, so a non-overlapping remote booking was
   silently dropped. Two distinct rows now both survive, and two nodes that saw
   the same row without disagreeing take the newer one.

Also corrected in the test: HLC fixtures use the real dash-separated format
`timestamp-counter-nodeId`, not the colon format in the sample, and the Class 1
assertion matches the list `merge()` returns.

Gate: `PolicyCatalogueTest` 21 passed / 70 assertions. Full suite 185 passed /
745 assertions, up from the 164 / 675 baseline.

---

## Task 4: Replace the placeholder merge dispatcher

`SyncMerger::merge()` is currently:

```php
public static function merge(array $local, array $remote, array $policy = []): array
{
    return array_merge($local, $remote);
}
```

Remote always wins, the policy argument is ignored entirely, and the return type
cannot express a conflict.

**Files:**
- Modify: `app/Sync/SyncMerger.php`

**Interfaces:**
- Consumes: `SyncScope`, `ConflictResult` from Task 3.
- Produces: `SyncMerger::merge(string $entityType, array $local, array $remote): array|ConflictResult` — the spec §5.3 signature.

- [x] **Step 1: Write the dispatcher**

```php
<?php

namespace App\Sync;

class SyncMerger
{
    /**
     * Pure merge. Identical inputs yield identical output on every node,
     * which is what makes it safe for a terminal and the cloud to merge the
     * same two changes independently and still converge.
     *
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>|ConflictResult
     */
    public static function merge(string $entityType, array $local, array $remote): array|ConflictResult
    {
        return SyncScope::policyFor($entityType)->merge($local, $remote);
    }
}
```

The signature now takes `entityType` first and no longer accepts a policy array.
That matches spec §5.3, with policy resolved from the catalogue rather than
supplied by the caller — a caller-supplied policy is how two nodes end up
disagreeing about how to merge the same rows.

- [x] **Step 2: Find and update every call site**

```powershell
rg -n "SyncMerger::merge" app/ tests/
```

Searched the whole repo outside `vendor/`: `SyncMerger` was defined but never
called, so there was nothing to update. It becomes reachable when Part B's
`SyncLogger` and Phase 4R's applier start driving it.

- [x] **Step 3: Run the gate**

```powershell
php artisan test
```

Expected: zero failures. `SyncMerger` had no call sites at all, so Step 2 changed nothing; the live baseline is 185 tests / 745 assertions.
Commit: `fix: dispatch merge through the policy catalogue instead of array_merge`

---

## Part A exit criteria

Do not begin Part B until all of these hold:

```powershell
python -c "import glob; [print(p, open(p,'rb').read().count(b'\x0a')) for p in sorted(glob.glob('app/Sync/Policies/*.php') + glob.glob('app/Models/Sync/*.php'))]"
php artisan migrate:fresh --force
php artisan test --filter=PolicyCatalogueTest
php artisan test
```

- Every file in `app/Sync/Policies/` and `app/Models/Sync/` reports real newlines.
- `migrate:fresh` reports `171 DONE, 0 FAIL` (165 baseline plus 6; Phase 1R is deferred, so its +1 has not been added).
- The catalogue test passes, including the Class 4 blocking case.
- The full suite has zero failures and zero skips.

Then continue with `2026-10-05-phase-2r-part-b-capture-and-convergence.md`.
