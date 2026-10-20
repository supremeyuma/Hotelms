<?php

namespace Tests\Feature\Sync;

use App\Sync\SyncEntityRegistry;
use App\Sync\TableKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards config/sync_scope.php's reference_snapshot block.
 *
 * The snapshot is the one path that moves reference data the sync engine
 * deliberately does not: users, roles, settings, the menu catalogue. These
 * tests prove the declared set is curated (not derived from no_capture_path,
 * which also holds transactional and derived tables), disjoint from the sync
 * registry (two transports must never race over one primary key), and safe to
 * write into an empty database where foreign keys are enforced (every FK
 * parent is available before its child arrives, and the declaration order
 * respects that).
 */
class ReferenceSnapshotScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function snapshot(): array
    {
        return (array) config('sync_scope.reference_snapshot', []);
    }

    public function test_reference_snapshot_is_declared_with_a_reason_for_every_table(): void
    {
        $snapshot = $this->snapshot();

        $this->assertNotEmpty(
            $snapshot,
            'config/sync_scope.php must declare a reference_snapshot table set; '
            .'GET /api/sync/reference has nothing to serve without it.'
        );

        foreach ($snapshot as $table => $reason) {
            $this->assertNotSame(
                '',
                trim((string) $reason),
                "Snapshot table [{$table}] is declared without a reason; every entry records why it travels."
            );
        }
    }

    /**
     * A snapshot table is one neither transport owns. If a table were in both,
     * the initial pull and the sync engine would race over the same primary
     * key with different write policies.
     */
    public function test_reference_snapshot_is_disjoint_from_the_sync_registry(): void
    {
        $overlap = array_values(array_intersect(
            array_keys($this->snapshot()),
            SyncEntityRegistry::keys()
        ));

        $this->assertSame(
            [],
            $overlap,
            'Tables in both the reference snapshot and SyncEntityRegistry: ['
            .implode(', ', $overlap).']. Two transports would race over one primary key.'
        );
    }

    public function test_every_snapshot_table_exists_and_yields_a_primary_key(): void
    {
        foreach (array_keys($this->snapshot()) as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Snapshot table [{$table}] does not exist in the schema."
            );

            $this->assertNotSame(
                [],
                TableKeys::primary($table),
                "Snapshot table [{$table}] must resolve a primary key so a payload row can be upserted by id."
            );
        }
    }

    /**
     * Derived, not hand-listed: every FK parent of every snapshot table must
     * be in the snapshot itself or arrive through sync (that is how
     * menu_inventory_recipes -> inventory_items is satisfied). A parent in
     * neither set means the pull would reference a row that does not exist.
     */
    public function test_every_foreign_key_parent_of_a_snapshot_table_is_available(): void
    {
        $snapshot = array_keys($this->snapshot());
        $available = array_merge($snapshot, SyncEntityRegistry::keys());

        foreach ($snapshot as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];

                $this->assertContains(
                    $parent,
                    $available,
                    "Table [{$table}] foreign-keys to [{$parent}], which is in neither the reference "
                    .'snapshot nor the sync registry, so the pull would reference a row that does not exist.'
                );
            }
        }
    }

    /**
     * The pull writes into a database where foreign keys are enforced
     * (DB_FOREIGN_KEYS defaults to true on sqlite), so a parent must be
     * declared before its child. Derived from the real schema, so no
     * hand-maintained pair list can go stale.
     */
    public function test_snapshot_tables_are_declared_after_their_in_snapshot_parents(): void
    {
        $order = array_keys($this->snapshot());

        foreach ($order as $position => $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];
                $parentPosition = array_search($parent, $order, true);

                if ($parentPosition === false) {
                    continue; // Parent arrives by sync, not by snapshot; ordering there is the engine's concern.
                }

                $this->assertLessThan(
                    $position,
                    $parentPosition,
                    "Snapshot table [{$table}] is declared before its foreign-key parent [{$parent}]; "
                    .'a pull into an FK-enforcing database would find no parent row to reference.'
                );
            }
        }
    }

    public function test_properties_is_snapshotted_and_not_in_the_sync_registry(): void
    {
        $this->assertArrayHasKey(
            'properties',
            $this->snapshot(),
            'properties must be snapshotted: synced rooms and room types reference it, and a terminal binds itself to one.'
        );

        $this->assertNotContains(
            'properties',
            SyncEntityRegistry::keys(),
            'properties is a single-property binding, not a synced table; it must stay out of the sync registry.'
        );
    }

    /**
     * What makes the set curated rather than derived from no_capture_path:
     * transactional rows and recomputed balances are never snapshotted.
     */
    public function test_transactional_and_derived_tables_are_not_snapshotted(): void
    {
        foreach (['charges', 'booking_rooms', 'inventory_stocks', 'drink_stock'] as $table) {
            $this->assertArrayNotHasKey(
                $table,
                $this->snapshot(),
                "Transactional/derived table [{$table}] must not be snapshotted."
            );
        }
    }

    /**
     * Its inventory_item_id parent arrives by sync, not by snapshot, so this
     * table alone may roll back on a first pull and complete on the next.
     * Declaring it last keeps that edge case to one table.
     */
    public function test_menu_inventory_recipes_is_declared_last(): void
    {
        $order = array_keys($this->snapshot());
        $last = end($order);

        $this->assertSame(
            'menu_inventory_recipes',
            $last,
            'menu_inventory_recipes must be declared last: its inventory_item_id parent arrives by sync.'
        );
    }
}
