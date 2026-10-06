# Phase 4S — Sync Scope Manifest and Tripwire

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisites:** [Phase 2R](2026-10-05-phase-2r-part-b-capture-and-convergence.md), [Phase 3](2026-10-05-phase-3-club-pos-repair.md) complete.

**Goal:** Define exactly which tables a terminal synchronizes, and make a test fail when application code touches a table the manifest does not cover.

**Architecture:** One declarative manifest of the transitive closure POS and inventory read and write. A tripwire test walks the actual model and query graph and compares it against the manifest, so adding a dependency without updating the manifest breaks the build rather than failing at 3am on a terminal with no network.

**Tech Stack:** Laravel 12, PHP 8.2+.

**Spec:** §7 (terminal synchronization scope).

**Closes:** G26.

**Why this is its own phase.** Spec §7 is a single sentence of requirement — "A
test fails if a new dependency is added that the manifest does not cover" — and no
plan implements it. A terminal that syncs 40 tables when it needed 20 wastes
bandwidth; one that syncs 15 when it needed 20 fails at the moment of sale, with
no network to recover from.

---

## Global Constraints

- **The manifest is derived from real code, not from the spec.** Spec §7 names
  "the folio/ledger tables" and "authentication and permission tables"; neither
  exists. `charges` + `journal_entries` + `journal_lines` is the ledger, `roles`
  is the whole authorization model. Phase 0R corrects the spec; this phase
  builds the manifest from the code.
- **Do not enumerate relationships by hand and hope.** Walk the graph, or the
  tripwire passes while the manifest is wrong.
- A table in the manifest that nothing touches is dead weight — remove it.
- Commit after every task.

---

## File Structure

**Created:**
- `config/sync_scope.php` — the manifest itself.
- `app/Sync/SyncScopeResolver.php` — walks the real dependency graph.
- `tests/Feature/Sync/SyncScopeTripwireTest.php` — the failing test.
- `tests/Feature/Sync/SyncScopeManifestTest.php` — asserts the manifest is minimal and complete.
- `docs/SYNC_SCOPE.md` — what syncs, what does not, and why.

**Responsibility note:** a manifest and the test that keeps it honest. No runtime
behaviour — Phase 4R consumes the manifest.

---

## Task 1: Map the real dependency closure

Start from what the terminal actually does, not from the table list.

**Files:**
- none yet.

**Interfaces:**
- Consumes: `ClubPosService`, `InventoryService`, `DrinkStockService`, POS controllers.
- Produces: the verified closure, before any code is written.

- [ ] **Step 1: Identify the entry points**

```powershell
rg -n "use App\\Models" app/Services/ClubPosService.php app/Services/InventoryService.php app/Services/DrinkStockService.php
rg -n "use App\\Models" app/Http/Controllers/ClubPos/
```

- [ ] **Step 2: Walk relationships transitively**

For each entry model, follow `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`
until the closure stops growing. Record table names, not class names.

Known from the audit, and to be confirmed:

| Table | Why a terminal needs it |
|---|---|
| `pos_dockets`, `pos_docket_items`, `pos_docket_payments` | The sale itself (Class 1) |
| `pos_sessions`, `pos_devices` | The shift lifecycle (Class 4) |
| `pos_print_logs` | Receipt history (Class 1) |
| `inventory_items`, `inventory_locations` | Menu and stock reference (Class 2) |
| `inventory_movements` | The stock ledger, authoritative (Class 1) |
| `inventory_stocks` | Current balance, derived (Class 3) |
| `drink_stock`, `drink_stock_movements` | Bar stock and its ledger |
| `menu_items`, `menu_categories`, `menu_subcategories` | What is sellable and priced |
| `menu_inventory_recipes` | Recipe to stock-item mapping |
| `bookings`, `booking_rooms` | `room_charge` needs a folio to post to |
| `charges` | The guest folio `room_charge` writes |
| `rooms`, `room_types` | Room state and pricing |
| `users`, `staff_profiles`, `roles` | Who is logged in and what they may do |
| `settings` | Cloud-authoritative configuration (Class 5) |

Two findings to verify: the spec says "folio/ledger tables" but the ledger is
`charges` plus `journal_entries`/`journal_lines`; and it says "permission tables"
but there is no `permissions` table — `roles` is the entire authorization model.

- [ ] **Step 3: Check what each table actually reads**

A table in the closure that is only ever read for a label does not need sync — a
terminal can cache static reference data at install time. But `settings`, `menu_items`
and `room_types` do change, so they sync.

Record, per table: which policy class, and whether it is read-only on a terminal.
The read-only set is a correctness requirement, not documentation — see the
manifest sample for why an ungated terminal can overwrite cloud configuration
with a stale clock and no conflict record.

- [ ] **Step 4: Verify `room_charge` specifically**

Spec §7 names this as the reason bookings must sync. Confirm the exact tables the
path touches:

```powershell
rg -n -A 12 "room_charge" app/Services/ClubPosService.php
```

Follow `BillingService::addCharge()` and record every table it writes.

Commit: `docs: document the real terminal sync closure before implementing it`

---

## Task 2: Write the manifest

**Files:**
- Create: `config/sync_scope.php`

**Interfaces:**
- Consumes: Task 1.
- Produces: `config('sync_scope.tables')`, `config('sync_scope.nodes')` and `config('sync_scope.read_only')`.
  - Phase 2R Part B `SyncLogger` consumes `read_only` as an outbound capture gate.
  - Phase 4R consumes `tables` when building a scope-filtered push query.
  - The tripwire consumes `tables`.

- [ ] **Step 1: Write the manifest**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Terminal Synchronization Scope
    |--------------------------------------------------------------------------
    |
    | The transitive closure of what Club POS and Inventory actually read and
    | write. A terminal does not synchronize the whole hotel database.
    |
    | This is the verified closure from docs/SYNC_SCOPE.md, not a guess.
    | The tripwire test fails when application code touches a table that is
    | not listed here, so adding a dependency is a deliberate act.
    |
    */

    'tables' => [
        // Class 1 - immutable facts, appended and never contested.
        'pos_dockets'            => 'merge',
        'pos_docket_items'       => 'merge',
        'pos_docket_payments'    => 'merge',
        'pos_print_logs'         => 'merge',
        'inventory_movements'    => 'merge',
        'drink_stock_movements'  => 'merge',
        'charges'                => 'merge',

        // Class 2 - mutable reference data, field-level last-write-wins.
        'inventory_items'        => 'lww',
        'inventory_locations'    => 'lww',
        'menu_items'             => 'lww',
        'menu_categories'        => 'lww',
        'menu_subcategories'     => 'lww',
        'menu_inventory_recipes' => 'lww',
        'rooms'                  => 'lww',
        'room_types'             => 'lww',
        'staff_profiles'         => 'lww',
        'users'                  => 'lww',
        'roles'                  => 'lww',

        // Class 3 - derived balances. Never authoritative; recomputed locally.
        'inventory_stocks'       => 'additive',
        'drink_stock'            => 'additive',

        // Class 4 - hard conflicts. Blocked and surfaced to a human.
        'bookings'               => 'conflict',
        'booking_rooms'          => 'conflict',
        'pos_sessions'           => 'conflict',

        // Class 5 - cloud-authoritative. Pushed down, never contested.
        'settings'               => 'push_down',
    ],

    /*
    |--------------------------------------------------------------------------
    | Node Types
    |--------------------------------------------------------------------------
    |
    | Which node kinds sync this scope. A staff client holds no database
    | (spec section 4.1), so it appears nowhere.
    |
    */

    'nodes' => ['cloud', 'lan_server', 'terminal'],

    /*
    |--------------------------------------------------------------------------
    | Read-Only Tables On A Terminal
    |--------------------------------------------------------------------------
    |
    | A terminal reads these for labels, prices and permissions. It must never
    | emit a sync change for them.
    |
    | This is not a style preference. Without this list a terminal that saves
    | a menu item - a stray edit, a failed import replay, an over-eager admin
    | action - emits a Class 2 change and fights the cloud over a menu price.
    | Last-write-wins means whoever wrote last wins, so an offline terminal
    | with a stale clock can overwrite live cloud configuration and nothing
    | will report a conflict.
    |
    | The cloud and the LAN server have no such restriction; only `terminal`
    | nodes are gated. Reference data is authored centrally and pushed down.
    |
    */

    'read_only' => [
        'menu_items',
        'menu_categories',
        'menu_subcategories',
        'menu_inventory_recipes',
        'room_types',
        'rooms',
        'inventory_locations',
        'staff_profiles',
        'users',
        'roles',
        'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Explicitly Excluded
    |--------------------------------------------------------------------------
    |
    | Recorded with a reason, so a reader does not assume it was an oversight.
    | Derived reporting projections are excluded deliberately: importing them
    | would import projection drift (spec section 6.1).
    |
    */

    'excluded' => [
        'reporting_pos_facts'          => 'Derived projection. Each node rebuilds from Class 1 source rows (spec 6.1).',
        'reporting_booking_facts'      => 'Derived projection (spec 6.1).',
        'reporting_room_daily_facts'   => 'Derived projection (spec 6.1).',
        'reporting_order_facts'        => 'Derived projection (spec 6.1).',
        'journal_entries'              => 'Manager and accounting surface, not needed to sell a drink. `charges` alone carry sale totals, so POS revenue reporting stays correct without the ledger.',
        'journal_lines'                => 'Manager and accounting surface.',
        'accounting_periods'           => 'Manager and accounting surface.',
        'events'                       => 'Not on a POS critical path.',
        'event_tickets'                => 'Not on a POS critical path.',
        'gallery_media'                => 'Static reference data cached at install.',
        'failed_jobs'                  => 'Local infrastructure.',
        'jobs'                         => 'Local infrastructure.',
    ],
];
```

- [ ] **Step 2: Cross-check against the Phase 2R policy catalogue**

Every table in the manifest must have a policy in `SyncScope::CATALOGUE`, and the
policy must agree. `charges` is the one addition Phase 2R did not cover — add it
to the catalogue as Class 1 in that plan's Task 3, or here if that plan has
already shipped.

```powershell
rg -n "'charges'" app/Sync/SyncScope.php config/sync_scope.php
```

- [ ] **Step 3: Verify the manifest agrees with the catalogue**

```php
public function test_every_manifest_table_has_a_matching_policy(): void
{
    foreach (array_keys(config('sync_scope.tables')) as $table) {
        $this->assertTrue(
            SyncScope::has($table),
            "Table [{$table}] is in the sync manifest but has no policy.",
        );
    }
}

public function test_policy_classes_match_the_manifest(): void
{
    $expected = [
        'merge'    => MergePolicy::class,
        'lww'      => LastWriteWinsPolicy::class,
        'additive' => AdditivePolicy::class,
        'conflict' => ConflictPolicy::class,
    ];

    foreach (config('sync_scope.tables') as $table => $policy) {
        $this->assertSame(
            $expected[$policy],
            SyncScope::policyFor($table)::class,
            "Policy mismatch for [{$table}].",
        );
    }
}
```

Commit: `feat: define the terminal synchronization scope manifest`

---

## Task 3: Build the resolver

A hand-written manifest drifts from the code. Walk the real graph.

**Files:**
- Create: `app/Sync/SyncScopeResolver.php`

**Interfaces:**
- Consumes: `config('sync_scope.tables')`, Eloquent relationships.
- Produces: `SyncScopeResolver::resolve(string $entryPoint): array` returning the transitive table set.

- [ ] **Step 1: Write the graph walker**

```php
<?php

namespace App\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the transitive table closure of a model's relationships.
 *
 * Used by the tripwire test to compare the manifest against what the code
 * actually reaches. A hand-maintained list drifts; this does not.
 */
class SyncScopeResolver
{
    /** @var array<string, true> */
    private array $seen = [];

    /** @var list<string> */
    private array $tables = [];

    /**
     * @param  class-string<Model>  $model
     * @return list<string>
     */
    public function closureFor(string $model, int $depth = 3): array
    {
        $this->seen = [];
        $this->tables = [];

        $this->walk($model, $depth);

        sort($this->tables);

        return array_values(array_diff($this->tables, ['migrations']));
    }

    private function walk(string $model, int $depth): void
    {
        if ($depth < 0 || isset($this->seen[$model])) {
            return;
        }

        $this->seen[$model] = true;

        if (! class_exists($model)) {
            return;
        }

        $instance = new $model;
        $table = $instance->getTable();

        if (Schema::hasTable($table)) {
            $this->tables[$table] = $this->tables[$table] ?? $table;
        }

        if ($depth === 0) {
            return;
        }

        foreach ($this->relations($instance) as $related) {
            $this->walk($related, $depth - 1);
        }
    }

    /** @return list<class-string<Model>> */
    private function relations(Model $model): array
    {
        $out = [];

        foreach ($model->getRelationships() as $name) {
            try {
                $relation = $model->{$name}();

                if (! $relation instanceof Relation) {
                    continue;
                }

                $related = $relation->getRelated();

                if ($related instanceof Model) {
                    $out[] = $related::class;
                }
            } catch (\Throwable) {
                // A relation that cannot be resolved outside a booted
                // application is not a terminal-sync dependency.
            }
        }

        return array_values(array_unique($out));
    }
}
```

- [ ] **Step 2: Choose the entry points and depth**

Depth 3 from `PosDocket`, `Booking` and `InventoryItem` is enough to reach
everything POS touches. Confirm by printing the closure and checking it against
the manifest rather than assuming.

```php
$resolver = new SyncScopeResolver();

foreach ([PosDocket::class, Booking::class, InventoryItem::class, MenuItem::class] as $entry) {
    echo $entry . ': ' . implode(', ', $resolver->closureFor($entry)) . PHP_EOL;
}
```

- [ ] **Step 3: Reconcile the closure against the manifest**

Anything the closure reaches that the manifest omits is a gap. Decide per table:
add it, or record why it is deliberately excluded.

Some will be legitimately excluded — an `Image` or `User` relation reaching
`media` is not a POS dependency. Record each exclusion with its reason.

Commit: `feat: resolve the real sync dependency closure from the model graph`

---

## Task 4: Write the tripwire

This is the deliverable spec §7 asks for.

**Files:**
- Create: `tests/Feature/Sync/SyncScopeTripwireTest.php`

**Interfaces:**
- Consumes: Task 2's manifest, Task 3's resolver.
- Produces: a failing test whenever code reaches outside the manifest.

- [ ] **Step 1: Write the tripwire**

```php
<?php

namespace Tests\Feature\Sync;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\PosDocket;
use App\Sync\SyncScopeResolver;
use Tests\TestCase;

class SyncScopeTripwireTest extends TestCase
{
    /**
     * Spec section 7: "A test fails if a new dependency is added that the
     * manifest does not cover."
     */
    public function test_every_table_pos_reaches_is_in_the_sync_manifest(): void
    {
        $manifest = array_keys(config('sync_scope.tables'));
        $excluded = array_keys(config('sync_scope.excluded'));
        $resolver = new SyncScopeResolver();

        $entries = [
            PosDocket::class,
            Booking::class,
            InventoryItem::class,
            MenuItem::class,
        ];

        $missing = [];

        foreach ($entries as $entry) {
            foreach ($resolver->closureFor($entry) as $table) {
                if (in_array($table, $manifest, true) || in_array($table, $excluded, true)) {
                    continue;
                }

                $missing[$table][] = class_basename($entry);
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Tables reached by POS but absent from the sync manifest:\n"
            . collect($missing)->map(fn ($from, $table) => "  {$table} (from {$from})")->implode("\n"),
        );
    }
}
```

- [ ] **Step 2: Verify it fails when a dependency is added**

A tripwire that has never failed is not a guard. Add a relationship temporarily
and confirm the test fails.

```php
// temporarily add to PosDocket
public function images()
{
    return $this->hasMany(\App\Models\Image::class);
}
```

Expected: the test fails naming the table. Remove it and confirm green.

- [ ] **Step 3: Verify the manifest has no dead entries**

The inverse check: a table in the manifest that nothing reaches is bandwidth
wasted on every sync.

```php
public function test_the_manifest_lists_no_unreachable_tables(): void
{
    $manifest = array_keys(config('sync_scope.tables'));
    $excluded = array_keys(config('sync_scope.excluded'));

    $resolver = new SyncScopeResolver();
    $reachable = [];

    foreach ([PosDocket::class, Booking::class, InventoryItem::class, MenuItem::class] as $entry) {
        $reachable = array_merge($reachable, $resolver->closureFor($entry));
    }

    $unreachable = array_diff($manifest, $reachable, $excluded);

    $this->assertSame(
        [],
        array_values($unreachable),
        'Manifest lists tables no POS entry point reaches: ' . implode(', ', $unreachable),
    );
}
```

`pos_devices` and `users` may legitimately be reachable only through a
non-relationship path. Either widen the resolver's entry points or move them to
an explicit `always_sync` list rather than leaving a false failure.

- [ ] **Step 4: Run**

```powershell
php artisan test --filter=SyncScope
```

Commit: `test: add a tripwire for the terminal sync scope manifest`

---

## Task 5: Document the scope

**Files:**
- Create: `docs/SYNC_SCOPE.md`

- [ ] **Step 1: Write the document**

Cover: what a terminal syncs and why, what it does not and why, the policy class
per table, which side is authoritative, and how to add a table (update the
manifest, the catalogue, and this document in one commit).

State plainly that reporting projections are excluded deliberately, and why:
importing them would import projection drift.

- [ ] **Step 2: Link it from the spec**

Add a reference from spec §7 to this document so the two do not drift apart.

Commit: `docs: document the terminal synchronization scope`

---

## Exit criteria

- `config/sync_scope.php` covers the verified closure with a policy per table.
- Every exclusion has a recorded reason.
- The tripwire fails on an added dependency and has been proven to do so.
- The inverse check passes, so the manifest has no dead entries.
- `docs/SYNC_SCOPE.md` matches the manifest.

Next: [Phase 4R — sync node runtime and transport](2026-10-05-phase-4r-sync-node-runtime-and-transport.md).
