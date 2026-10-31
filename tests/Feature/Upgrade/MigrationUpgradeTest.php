<?php

namespace Tests\Feature\Upgrade;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves an existing installation can be upgraded in place.
 *
 * Every other suite starts from an empty database, which says nothing about a
 * hotel that has been running for weeks. This test:
 *
 *   1. builds a database from the migrations that existed before the October
 *      sync/print phase (a realistic "installed last month" snapshot),
 *   2. writes representative customer data into it,
 *   3. runs the real migration set on top,
 *   4. asserts the data survived and the new schema and reference data arrived.
 */
class MigrationUpgradeTest extends TestCase
{
    private string $workDir;
    private string $database;
    private string $baselineDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = storage_path('framework/testing/upgrade-' . uniqid());
        $this->baselineDir = $this->workDir . DIRECTORY_SEPARATOR . 'migrations';
        $this->database = $this->workDir . DIRECTORY_SEPARATOR . 'upgrade.sqlite';

        File::ensureDirectoryExists($this->baselineDir);
        touch($this->database);

        config([
            'database.connections.upgrade_test' => [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('upgrade_test');

        // Baseline: everything released before the October sync phase.
        foreach (File::files(database_path('migrations')) as $file) {
            if (strcmp($file->getFilename(), '2026_10_01') >= 0) {
                continue;
            }
            File::copy($file->getPathname(), $this->baselineDir . DIRECTORY_SEPARATOR . $file->getFilename());
        }
    }

    protected function tearDown(): void
    {
        DB::purge('upgrade_test');
        File::deleteDirectory($this->workDir);

        parent::tearDown();
    }

    public function test_an_existing_installation_upgrades_without_losing_data(): void
    {
        // --- 1. The old schema ------------------------------------------------
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'upgrade_test',
            '--path' => $this->baselineDir,
            '--realpath' => true,
            '--force' => true,
        ]), 'the baseline migrations must apply');
        DB::purge('upgrade_test');

        $this->assertFalse(
            Schema::connection('upgrade_test')->hasTable('sync_nodes'),
            'the baseline must predate the sync tables',
        );

        // --- 2. Customer data written against the old schema ------------------
        $connection = DB::connection('upgrade_test');
        $now = now();

        $connection->table('settings')->insert([
            'key' => 'upgrade.probe',
            'value' => 'survives',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = $connection->table('users')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Upgrade Guest',
            'email' => 'upgrade-probe@example.test',
            'password' => bcrypt('secret'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $propertyId = $connection->table('properties')->insertGetId([
            'name' => 'Upgrade Property',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // --- 3. Upgrade to the current schema ---------------------------------
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'upgrade_test',
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
        ]), 'the upgrade migrations must apply');
        DB::purge('upgrade_test');

        $schema = Schema::connection('upgrade_test');
        $connection = DB::connection('upgrade_test');

        // --- 4. Data survived -------------------------------------------------
        $this->assertSame(
            'survives',
            $connection->table('settings')->where('key', 'upgrade.probe')->value('value'),
        );
        $this->assertSame(
            'Upgrade Guest',
            $connection->table('users')->where('id', $userId)->value('name'),
        );
        $this->assertSame(
            'Upgrade Property',
            $connection->table('properties')->where('id', $propertyId)->value('name'),
        );

        // --- 5. The new schema arrived ---------------------------------------
        $this->assertTrue($schema->hasTable('sync_nodes'));
        $this->assertTrue($schema->hasTable('sync_pairing_codes'));
        $this->assertTrue($schema->hasTable('print_jobs'));
        $this->assertTrue($schema->hasColumn('rooms', 'sync_uid'));

        // --- 6. Reference data the upgraded app depends on --------------------
        $this->assertSame(4, $connection->table('inventory_locations')->count());
        $this->assertSame(8, $connection->table('accounts')->count());

        // Every migration is recorded, so a second upgrade is a no-op.
        $applied = $connection->table('migrations')->pluck('migration')->all();
        $this->assertContains('2026_10_29_000001_seed_default_inventory_locations', $applied);
        $this->assertContains('2026_10_30_000001_seed_chart_of_accounts', $applied);

        $before = count($applied);
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'upgrade_test',
            '--path' => database_path('migrations'),
            '--realpath' => true,
            '--force' => true,
        ]), 'a second upgrade run must succeed');
        DB::purge('upgrade_test');

        $this->assertSame(
            $before,
            DB::connection('upgrade_test')->table('migrations')->count(),
            'a second upgrade must not re-run migrations',
        );
    }
}
