<?php

namespace Tests\Feature\Sync;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\PosDocket;
use App\Sync\SyncScope;
use App\Sync\SyncScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guard spec section 7 asks for: "A test fails if a new dependency is added
 * that the manifest does not cover."
 *
 * The manifest in config/sync_scope.php is the verified closure of what Club
 * POS and Inventory read and write. This test walks the real model graph from
 * the POS entry points and fails when a reachable table is neither in the
 * manifest nor recorded as deliberately excluded. A terminal that syncs too
 * little fails at the moment of sale with no network to recover from, so the
 * guard errs toward failing the build.
 */
class SyncScopeTripwireTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Spec section 6.2: the modules in flow are the seed of the closure. Menu
     * and inventory are included because POS reads both while selling.
     *
     * @var list<class-string>
     */
    private array $entryPoints = [
        PosDocket::class,
        Booking::class,
        InventoryItem::class,
        MenuItem::class,
    ];

    /**
     * @return array<string, list<string>> reachable table => entry points that reach it
     */
    private function reachableByEntry(): array
    {
        $resolver = new SyncScopeResolver;
        $byTable = [];

        foreach ($this->entryPoints as $model) {
            foreach ($resolver->closureFor($model) as $table) {
                $byTable[$table][] = class_basename($model);
            }
        }

        ksort($byTable);

        return $byTable;
    }

    public function test_every_reachable_table_is_listed_or_recorded_as_excluded(): void
    {
        $tables = array_keys(config('sync_scope.tables'));
        $excluded = array_keys(config('sync_scope.excluded'));

        $missing = [];

        foreach ($this->reachableByEntry() as $table => $entries) {
            if (in_array($table, $tables, true) || in_array($table, $excluded, true)) {
                continue;
            }

            $missing[$table] = $entries;
        }

        $report = collect($missing)
            ->map(fn (array $from, string $table) => "  {$table} (from ".implode(', ', array_unique($from)).')')
            ->implode("\n");

        $this->assertSame(
            [],
            $missing,
            "Application code reaches tables the sync scope has not decided:\n{$report}\n"
            .'Add each to config/sync_scope.php `tables`, or record why it is `excluded`.'
        );
    }

    public function test_the_tripwire_reports_a_new_relation_to_an_undecided_table(): void
    {
        $tables = array_keys(config('sync_scope.tables'));
        $excluded = array_keys(config('sync_scope.excluded'));

        // A docket trades in guest requests in this hypothetical, and nobody has
        // decided where guest_requests belongs. The guard must flag it.
        $reachable = [...array_keys($this->reachableByEntry()), 'guest_requests'];

        $uncovered = array_values(array_diff($reachable, $tables, $excluded));

        $this->assertContains('guest_requests', $uncovered);
        $this->assertNotSame([], $uncovered);
    }

    public function test_read_only_tables_are_in_scope_only_for_non_terminal_nodes(): void
    {
        config(['sync.node_type' => 'terminal']);

        foreach ($this->readOnlyTables() as $table) {
            $this->assertFalse(SyncScope::mayEmit($table), "Terminal must not emit read-only table [{$table}].");
        }

        config(['sync.node_type' => 'cloud']);

        foreach ($this->readOnlyTables() as $table) {
            $this->assertTrue(SyncScope::mayEmit($table), "Cloud node must be allowed to write table [{$table}].");
        }
    }

    public function test_next_delivery_tables_are_marked_read_only_on_a_terminal(): void
    {
        // Spec section 6.1: a terminal reads menu, room and permission lookups
        // but must never emit them. If the manifest drops one of these from the
        // read-only set the mapping is a deliberate change, so surface it rather
        // than silently not enforcing it.
        $terminalReference = [
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
        ];

        foreach ($terminalReference as $table) {
            $this->assertContains($table, $this->readOnlyTables());
        }
    }

    /** @return list<string> */
    private function readOnlyTables(): array
    {
        return array_values((array) config('sync_scope.read_only'));
    }
}