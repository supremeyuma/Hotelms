<?php

namespace Tests\Feature\Sync;

use App\Models\InventoryItem;
use App\Models\User;
use App\Sync\ReferenceSnapshotApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pins the write side of the reference pull: takes the exact body served by
 * GET /api/sync/reference and writes it into this node.
 *
 * The applier must be idempotent by primary key (ids preserved), must never
 * re-hash a password or mint a local sync change, must isolate a failing table
 * without abandoning the rest, and must only delete when `--prune` is asked
 * for. Tables the config does not declare are never written, whatever the
 * payload says.
 */
class ReferenceSnapshotApplyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return array{applied: array<string, int>, failed: array<string, string>}
     */
    private function apply(array $tables, bool $prune = false): array
    {
        return app(ReferenceSnapshotApplier::class)->apply([
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables,
        ], $prune);
    }

    public function test_rows_from_the_payload_land_locally(): void
    {
        $result = $this->apply([
            'properties' => [
                ['id' => 1, 'name' => 'Lakeside Hotel'],
            ],
            'menu_categories' => [
                ['id' => 1, 'name' => 'Kitchen', 'type' => 'kitchen'],
            ],
        ]);

        $this->assertDatabaseHas('properties', ['id' => 1, 'name' => 'Lakeside Hotel']);
        $this->assertDatabaseHas('menu_categories', ['id' => 1, 'name' => 'Kitchen', 'type' => 'kitchen']);

        $this->assertSame(1, $result['applied']['properties']);
        $this->assertSame(1, $result['applied']['menu_categories']);
        $this->assertSame([], $result['failed']);
    }

    public function test_a_snapshotted_password_is_written_verbatim_and_still_authenticates(): void
    {
        $hash = Hash::make('secret');

        $this->apply([
            'users' => [
                [
                    'id' => 7,
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Ada Okafor',
                    'email' => 'ada@example.test',
                    'password' => $hash,
                ],
            ],
        ]);

        $user = User::where('email', 'ada@example.test')->firstOrFail();

        $this->assertSame($hash, $user->password);
        $this->assertTrue(Hash::check('secret', $user->password));
    }

    /**
     * The password assertion alone cannot tell a query-builder write from a
     * model write: User::setPasswordAttribute already short-circuits an
     * existing hash, and `users` has no capture path. This tripwire runs while
     * the applier writes `users`: a model save fires `eloquent.saving` and
     * fails the apply, while a query-builder upsert never fires it.
     */
    public function test_applying_users_does_not_raise_model_events(): void
    {
        Event::listen('eloquent.saving: '.User::class, function (): void {
            throw new RuntimeException('The applier saved a User model; it must use the query builder.');
        });

        $result = $this->apply([
            'users' => [
                [
                    'id' => 7,
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Ada Okafor',
                    'email' => 'ada@example.test',
                    'password' => Hash::make('secret'),
                ],
            ],
        ]);

        $this->assertArrayHasKey('users', $result['applied']);
        $this->assertArrayNotHasKey('users', $result['failed']);
        $this->assertDatabaseHas('users', ['id' => 7, 'email' => 'ada@example.test']);
    }

    /**
     * The change log is `sync_changes`, the same table ChangeLogCaptureTest
     * inspects. Reference tables are read-only on a terminal: applying the
     * snapshot must not manufacture changes that the terminal then pushes
     * back at the node it pulled from.
     */
    public function test_applying_reference_rows_appends_no_sync_changes(): void
    {
        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-terminal',
        ]);

        // Control: capture is live in this process, so a zero below is the
        // applier's doing, not a disabled logger.
        InventoryItem::create(['name' => 'Control', 'sku' => 'CTRL-1', 'unit' => 'kg']);
        $this->assertSame(1, DB::table('sync_changes')->count());

        $this->apply([
            'users' => [
                [
                    'id' => 1,
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Ada Okafor',
                    'email' => 'ada@example.test',
                    'password' => Hash::make('secret'),
                ],
            ],
            'settings' => [
                ['id' => 1, 'key' => 'booking.cutoff_minutes', 'value' => '120'],
            ],
        ]);

        $this->assertSame(1, DB::table('sync_changes')->count());
        $this->assertSame(
            0,
            DB::table('sync_changes')->whereIn('table_name', ['users', 'settings'])->count(),
        );
    }

    /**
     * menu_inventory_recipes points at inventory items that arrive by sync,
     * not by snapshot, so on a first pull it can legitimately fail. That must
     * roll back only its own table and leave the rest applied.
     */
    public function test_a_failing_table_does_not_stop_the_rest_and_leaves_no_partial_row(): void
    {
        $result = $this->apply([
            'roles' => [
                ['id' => 1, 'name' => 'Manager', 'slug' => 'manager', 'guard_name' => 'web'],
            ],
            'menu_categories' => [
                ['id' => 1, 'name' => 'Kitchen', 'type' => 'kitchen'],
            ],
            'menu_items' => [
                [
                    'id' => 1,
                    'menu_category_id' => 1,
                    'name' => 'Jollof Rice',
                    'price' => 2500,
                    'service_area' => 'kitchen',
                ],
            ],
            'menu_inventory_recipes' => [
                [
                    'id' => 1,
                    'menu_item_id' => 1,
                    'inventory_item_id' => 999999,
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertArrayHasKey('roles', $result['applied']);
        $this->assertArrayHasKey('menu_items', $result['applied']);
        $this->assertDatabaseHas('roles', ['id' => 1, 'name' => 'Manager']);

        $this->assertArrayHasKey('menu_inventory_recipes', $result['failed']);
        $this->assertIsString($result['failed']['menu_inventory_recipes']);
        $this->assertArrayNotHasKey('menu_inventory_recipes', $result['applied']);
        $this->assertSame(0, DB::table('menu_inventory_recipes')->count());
    }

    public function test_applying_the_same_payload_twice_changes_nothing(): void
    {
        $tables = [
            'properties' => [
                ['id' => 1, 'name' => 'Lakeside Hotel'],
            ],
            'menu_categories' => [
                ['id' => 1, 'name' => 'Kitchen', 'type' => 'kitchen'],
            ],
        ];

        $this->apply($tables);

        $counts = [
            'properties' => DB::table('properties')->count(),
            'menu_categories' => DB::table('menu_categories')->count(),
        ];
        $ids = [
            'properties' => DB::table('properties')->orderBy('id')->pluck('id')->all(),
            'menu_categories' => DB::table('menu_categories')->orderBy('id')->pluck('id')->all(),
        ];

        $this->apply($tables);

        $this->assertSame($counts, [
            'properties' => DB::table('properties')->count(),
            'menu_categories' => DB::table('menu_categories')->count(),
        ]);
        $this->assertSame($ids, [
            'properties' => DB::table('properties')->orderBy('id')->pluck('id')->all(),
            'menu_categories' => DB::table('menu_categories')->orderBy('id')->pluck('id')->all(),
        ]);
    }

    /**
     * role_has_permissions is entirely primary key, so the update list is empty
     * and the applier's fallback (all columns) is the only thing that keeps the
     * second apply from degrading to a duplicate insert.
     */
    public function test_a_key_only_pivot_survives_a_second_apply(): void
    {
        $this->apply([
            'roles' => [
                ['id' => 1, 'name' => 'Manager', 'slug' => 'manager', 'guard_name' => 'web'],
            ],
            'permissions' => [
                ['id' => 1, 'name' => 'snapshot.permission', 'guard_name' => 'web'],
            ],
        ]);

        $tables = [
            'role_has_permissions' => [
                ['permission_id' => 1, 'role_id' => 1],
            ],
        ];

        $first = $this->apply($tables);

        $this->assertSame(1, $first['applied']['role_has_permissions']);
        $this->assertSame(1, DB::table('role_has_permissions')->count());

        $second = $this->apply($tables);

        $this->assertArrayNotHasKey('role_has_permissions', $second['failed']);
        $this->assertSame(1, $second['applied']['role_has_permissions']);
        $this->assertSame(1, DB::table('role_has_permissions')->count());
    }

    /**
     * The applier iterates the config's declared tables, never the payload's
     * keys. A table the config does not name is ignored with no write and no
     * error, even when the local schema has it.
     */
    public function test_tables_outside_the_snapshot_set_are_ignored(): void
    {
        $result = $this->apply([
            'charges' => [
                ['id' => 1, 'amount' => 5000, 'description' => 'not part of the snapshot'],
            ],
            'not_a_table' => [
                ['id' => 1],
            ],
        ]);

        $this->assertSame([], $result['applied']);
        $this->assertSame([], $result['failed']);
        $this->assertSame(0, DB::table('charges')->count());
        $this->assertFalse(Schema::hasTable('not_a_table'));
    }

    public function test_prune_deletes_only_when_asked_and_keeps_rows_the_payload_still_carries(): void
    {
        DB::table('properties')->insert(['id' => 2, 'name' => 'Local only']);

        $tables = [
            'properties' => [
                ['id' => 1, 'name' => 'From peer'],
            ],
        ];

        // Default: local rows the payload does not carry survive.
        $this->apply($tables);
        $this->assertDatabaseHas('properties', ['id' => 1]);
        $this->assertDatabaseHas('properties', ['id' => 2]);

        // Explicit prune: the absent row goes, the carried row stays.
        $this->apply($tables, true);
        $this->assertDatabaseHas('properties', ['id' => 1]);
        $this->assertDatabaseMissing('properties', ['id' => 2]);
    }

    /**
     * Spatie resolves permissions from a cache primed before the pull. Without
     * an explicit forget, a permission that arrives in the payload stays
     * invisible to authorization until the 24-hour cache expires.
     */
    public function test_the_permission_cache_is_forgotten_after_a_pull(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->assertFalse(
            $registrar->getPermissions()->pluck('name')->contains('snapshot.permission'),
        );

        $this->apply([
            'permissions' => [
                ['id' => 1, 'name' => 'snapshot.permission', 'guard_name' => 'web'],
            ],
        ]);

        $this->assertTrue(
            $registrar->getPermissions()->pluck('name')->contains('snapshot.permission'),
        );
    }
}
