<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Three genuinely separate file-backed SQLite databases.
 *
 * Two `:memory:` connections resolve to one handle, which makes a multi-node
 * test silently meaningless. These are real files on disk, each with its own
 * migrations and its own change log.
 *
 * Peering is a full mesh: every node is configured with the other two, because
 * the applier deliberately does not re-log a remote change, so nothing relays.
 * A change only reaches a node that is directly peered with its origin.
 */
class ThreeNodeSyncHarness
{
    private const LETTERS = ['a', 'b', 'c'];

    /** @var array<string, string> */
    private array $paths;

    private function __construct(
        private readonly string $dir,
        array $paths,
    ) {
        $this->paths = $paths;
    }

    public static function make(): self
    {
        $dir = sys_get_temp_dir() . '/hotelms-3node-' . bin2hex(random_bytes(6));

        if (! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new \RuntimeException("Could not create harness directory [{$dir}].");
        }

        $paths = [];

        foreach (self::LETTERS as $letter) {
            $path = $dir . "/node-{$letter}.sqlite";
            touch($path);
            $paths[$letter] = $path;

            Config::set('database.connections.node_' . $letter, [
                'driver'                  => 'sqlite',
                'database'                => $path,
                'prefix'                  => '',
                'foreign_key_constraints' => true,
            ]);
        }

        return new self($dir, $paths);
    }

    public function migrateAll(): void
    {
        $original = DB::getDefaultConnection();

        try {
            foreach (self::LETTERS as $letter) {
                $this->use($letter);
                Artisan::call('migrate:fresh', ['--force' => true]);
            }
        } finally {
            DB::setDefaultConnection($original);
        }
    }

    public function use(string $letter): void
    {
        $connection = 'node_' . $letter;

        DB::purge($connection);
        DB::setDefaultConnection($connection);

        Config::set('sync.node_id', 'node-' . $letter);
        Config::set('sync.peers', $this->peersFor($letter));
    }

    /**
     * Run a callback while this node is the active one, then restore.
     *
     * The test stands in for the network by invoking the peer's controller
     * directly, so it needs to borrow that peer's database and identity for the
     * duration and hand both back before the caller resumes writing cursors.
     */
    public function onNode(string $letter, callable $callback): mixed
    {
        $previousConnection = DB::getDefaultConnection();
        $previousNodeId = config('sync.node_id');
        $previousPeers = config('sync.peers');

        $this->use($letter);

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($previousConnection);
            Config::set('sync.node_id', $previousNodeId);
            Config::set('sync.peers', $previousPeers);
        }
    }

    /**
     * @return array<string, array{base_url: string}>
     */
    private function peersFor(string $letter): array
    {
        $peers = [];

        foreach (self::LETTERS as $other) {
            if ($other === $letter) {
                continue;
            }

            $peers['node-' . $other] = ['base_url' => 'http://node-' . $other . '.test'];
        }

        return $peers;
    }

    /**
     * @return array<string, string>  host => letter
     */
    public function hostMap(): array
    {
        $map = [];

        foreach (self::LETTERS as $letter) {
            $map['node-' . $letter . '.test'] = $letter;
        }

        return $map;
    }

    /**
     * The business contents of a node's table, for cross-node comparison.
     *
     * @return array<int, array<string, mixed>>
     */
    public function snapshot(string $letter, string $table = 'inventory_items'): array
    {
        $this->use($letter);

        $rows = DB::table($table)
            ->orderBy($table === 'inventory_items' ? 'sku' : 'id')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        foreach ($rows as $i => $row) {
            unset($rows[$i]['id'], $rows[$i]['sync_hlc'], $rows[$i]['sync_origin_node_id']);
        }

        return $rows;
    }

    public function cleanup(): void
    {
        foreach (self::LETTERS as $letter) {
            DB::purge('node_' . $letter);
        }

        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }
}
