<?php

namespace App\Console\Commands;

use App\Models\Sync\SyncNode;
use App\Sync\HttpSyncTransport;
use App\Sync\ReferenceSnapshotApplier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pull the reference snapshot a freshly installed terminal cannot receive
 * through sync.
 *
 * Sync moves transactional changes between nodes that already agree on their
 * reference data. It deliberately cannot move the reference data itself:
 * users, roles, permissions, settings, the menu catalogue and the POS device
 * registry are authored centrally and have no capture path in
 * SyncEntityRegistry. A terminal authenticates against its own copy of the
 * staff accounts and sells from its own copy of the menu, so without this pull
 * a new install has nobody who can log in and nothing to sell. This command is
 * how that data arrives.
 *
 * The FIRST peer that answers wins. Peers are tried in preference order - the
 * LAN server before the cloud (spec §4.3), so a local pull is cheap, and the
 * cloud is only reached when the LAN server is silent. Once a peer returns a
 * snapshot there is nothing for a fallback to do: the snapshot is a complete,
 * authoritative set, and pulling the next peer would only overwrite it with a
 * staler copy.
 *
 * A table that fails is a warning, not a failure. menu_inventory_recipes
 * points at inventory items that arrive by sync, not by snapshot, so on a
 * fresh install it legitimately cannot be written until those items land - the
 * table is declared last for exactly that reason (config/sync_scope.php). The
 * command fails only when nothing at all could be applied, because failing an
 * install where every account and menu item arrived would be a worse lie than
 * a warning about the one recipe table that will complete on the next pull.
 */
class SyncRefreshReference extends Command
{
    protected $signature = 'sync:refresh-reference
        {--peer= : Pull from one peer by id or base URL}
        {--prune : Delete local rows the source no longer has}';

    protected $description = 'Pull reference data (users, roles, settings, menu) from a peer';

    public function handle(HttpSyncTransport $transport, ReferenceSnapshotApplier $applier): int
    {
        if (! config('sync.enabled')) {
            $this->warn('Sync is disabled. Set SYNC_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $peers = $this->resolvePeers();

        if ($peers === []) {
            $this->error('No peers are configured. Set SYNC_PEERS to a peer base URL.');

            return self::FAILURE;
        }

        $appliedAnything = false;

        foreach ($peers as $peerId => $baseUrl) {
            try {
                $payload = $transport->reference($baseUrl);
            } catch (Throwable $e) {
                $this->warn("unreachable: {$peerId} ({$e->getMessage()})");

                continue;
            }

            $result = $applier->apply($payload, (bool) $this->option('prune'));

            $applied = $result['applied'];
            $failed = $result['failed'];

            $rows = array_sum($applied);

            if ($applied !== []) {
                $this->info(sprintf(
                    'Pulled %d rows across %d tables from %s.',
                    $rows,
                    count($applied),
                    $peerId,
                ));

                $appliedAnything = true;
            }

            foreach ($failed as $table => $reason) {
                $this->warn("failed: {$table} ({$reason})");
            }

            if ($appliedAnything) {
                return self::SUCCESS;
            }
        }

        $this->error('Nothing was applied from any peer.');

        return self::FAILURE;
    }

    /**
     * Configured peers in preference order, keyed by node id.
     *
     * This deliberately duplicates SyncCycleRunner::resolvePeers rather than
     * sharing it. The runner takes peers in the configured order and applies no
     * role preference - it is running a mesh cycle where every peer gets
     * visited. This command pulls from ONE peer and must prefer the LAN server
     * over the cloud (spec §4.3), so it ranks by the local sync_nodes.type,
     * which the runner has no reason to read. The two rules are not the same
     * rule, so they are not the same code.
     *
     * config/sync.php normalises `sync.peers` to `array<node_id, {base_url,
     * credential}>`; a bare `SYNC_PEERS=<url>` is keyed by the URL itself.
     * `--peer` matches either the node id or the base URL.
     *
     * @return array<string, string>
     */
    private function resolvePeers(): array
    {
        $onlyPeer = $this->option('peer') ?: null;

        $resolved = [];

        foreach (config('sync.peers', []) as $peerId => $peerConfig) {
            if (is_array($peerConfig)) {
                $peerId = (string) $peerId;
                $baseUrl = $peerConfig['base_url'] ?? null;
            } else {
                $baseUrl = $peerConfig;
                $peerId = (string) $peerConfig;
            }

            if (! is_string($baseUrl) || $baseUrl === '') {
                continue;
            }

            if ($onlyPeer !== null && $onlyPeer !== $peerId && $onlyPeer !== $baseUrl) {
                continue;
            }

            $resolved[$peerId] = $baseUrl;
        }

        if ($onlyPeer !== null) {
            return $resolved;
        }

        return $this->inPreferenceOrder($resolved);
    }

    /**
     * Rank peers by their local sync_nodes.type: a LAN server (0) before the
     * cloud (1) before anything unknown or unregistered (2). uksort is stable
     * in PHP 8, so peers of equal rank keep the configured order, which
     * config/sync.php already documents as "ordered by preference".
     *
     * @param  array<string, string>  $peers
     * @return array<string, string>
     */
    private function inPreferenceOrder(array $peers): array
    {
        if (count($peers) < 2) {
            return $peers;
        }

        $types = SyncNode::query()
            ->whereIn('node_id', array_keys($peers))
            ->pluck('type', 'node_id');

        uksort($peers, fn (string $a, string $b): int => $this->rank($types[$a] ?? null) <=> $this->rank($types[$b] ?? null));

        return $peers;
    }

    private function rank(?string $type): int
    {
        return match ($type) {
            'lan_server' => 0,
            'cloud' => 1,
            default => 2,
        };
    }
}
