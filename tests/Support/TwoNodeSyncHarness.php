<?php

namespace Tests\Support;

use App\Models\Sync\SyncChange;
use App\Sync\SyncApplier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Two genuinely separate file-backed SQLite databases.
 *
 * Two `:memory:` connections resolve to one handle, which makes a two-node
 * test silently meaningless: both "nodes" share every row. These are real
 * files on disk, each with its own migrations and its own change log.
 */
class TwoNodeSyncHarness
{
    private const CONNECTIONS = ['node_a', 'node_b'];

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
                'driver'                  => 'sqlite',
                'database'                => $path,
                'prefix'                  => '',
                'foreign_key_constraints' => true,
            ]);
        }

        return new self($dir, $a, $b);
    }

    public function migrateBoth(): void
    {
        $original = DB::getDefaultConnection();

        try {
            foreach (self::CONNECTIONS as $name) {
                DB::purge($name);
                DB::setDefaultConnection($name);

                Artisan::call('migrate:fresh', ['--force' => true]);
            }
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    public function use(string $node): void
    {
        $connection = $node === 'a' ? 'node_a' : 'node_b';

        DB::purge($connection);
        DB::setDefaultConnection($connection);
    }

    /**
     * Hand one node's change log to the other and apply it.
     *
     * In-process stand-in for the HTTP transport Phase 4R builds. Both nodes
     * are the same PHP process here, which is fine: what is being proved is
     * that merge converges, not that the network works.
     *
     * This lives on the harness rather than in the test classes because
     * `syncBothWays()` has to reach it, and because Phase 4R replaces exactly
     * one method rather than one per test.
     */
    public function exchange(string $from, string $to): void
    {
        $this->use($from);

        $changes = SyncChange::query()
            ->orderBy('sequence')
            ->get()
            ->map(fn (SyncChange $change): array => [
                'table_name' => $change->table_name,
                'row_id'     => $change->row_id,
                'operation'  => $change->operation,
                'node_id'    => $change->node_id,
                'hlc'        => $change->hlc,
                'payload'    => $change->payload,
            ])
            ->all();

        $this->use($to);

        foreach ($changes as $change) {
            app(SyncApplier::class)->apply(new SyncChange($change));
        }
    }

    public function syncBothWays(): void
    {
        $this->exchange('a', 'b');
        $this->exchange('b', 'a');
    }

    /**
     * The business contents of a node's table, for cross-node comparison.
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(string $node, string $table = 'inventory_items'): array
    {
        $this->use($node);

        $rows = DB::table($table)
            ->orderBy($table === 'inventory_items' ? 'sku' : 'id')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        foreach ($rows as $i => $row) {
            // `id` is this node's own auto-increment and diverges as soon as
            // the two nodes write independently - the reason sync_uid exists
            // at all. `sync_hlc` and `sync_origin_node_id` name the node that
            // last wrote the row, so they differ by construction. Everything
            // left - business columns, sync_uid, sync_deleted_at and both
            // timestamps - must match for the nodes to be considered converged.
            unset($rows[$i]['id'], $rows[$i]['sync_hlc'], $rows[$i]['sync_origin_node_id']);
        }

        return $rows;
    }

    public function cleanup(): void
    {
        foreach (self::CONNECTIONS as $name) {
            DB::purge($name);
        }

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
