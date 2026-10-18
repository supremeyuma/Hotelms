<?php

namespace App\Console\Commands;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncCursor;
use App\Models\Sync\SyncNode;
use Illuminate\Console\Command;

class SyncStatus extends Command
{
    protected $signature = 'sync:status';

    protected $description = 'Show node identity, peers, cursors and pending change count';

    public function handle(): int
    {
        $pendingByPeer = $this->pendingByPeer();

        $this->table(
            ['Setting', 'Value'],
            [
                ['enabled',    (string) var_export(config('sync.enabled'), true)],
                ['node_id',    (string) config('sync.node_id')],
                ['node_type',  (string) config('sync.node_type')],
                ['peers',      $this->peerSummary()],
                // The work at least one configured peer has not acknowledged:
                // the largest per-peer count below. Zero peers means there is
                // nowhere to deliver, so nothing is pending delivery.
                ['pending',    (string) ($pendingByPeer === [] ? 0 : max($pendingByPeer))],
                ['changes',    (string) SyncChange::count()],
                ['registered', (string) SyncNode::count()],
            ],
        );

        if ($pendingByPeer !== []) {
            $this->table(
                ['Peer', 'Pending', 'Last pushed'],
                collect($pendingByPeer)->map(fn (int $pending, string $peerId) => [
                    $peerId,
                    (string) $pending,
                    (string) ($this->outboundLastSyncedAt($peerId) ?? '-'),
                ])->values()->all(),
            );
        }

        $cursors = SyncCursor::all();

        if ($cursors->isNotEmpty()) {
            $this->table(
                ['Peer', 'Direction', 'Last applied change', 'Last synced'],
                $cursors->map(fn (SyncCursor $c) => [
                    $c->target_node_id,
                    $c->direction,
                    (string) $c->last_applied_change_id,
                    (string) $c->last_synced_at,
                ])->all(),
            );
        }

        return self::SUCCESS;
    }

    /**
     * Local changes each configured peer has not yet acknowledged, keyed by
     * peer id.
     *
     * The outbound cursor is the acknowledgement: NodeSyncService advances it
     * only after the peer confirms a push. A missing cursor row means nothing
     * has ever been acknowledged, so the baseline is 0 — the safe reading.
     * The inbound cursor indexes the peer's log rather than ours, so it never
     * suppresses this node's pending work.
     *
     * @return array<string, int>
     */
    private function pendingByPeer(): array
    {
        $self = (string) config('sync.node_id');

        $pending = [];

        foreach ($this->peerIds() as $peerId) {
            $acknowledged = (int) (SyncCursor::query()
                ->where('node_id', $self)
                ->where('target_node_id', $peerId)
                ->where('direction', 'out')
                ->value('last_applied_change_id') ?? 0);

            $pending[$peerId] = SyncChange::query()
                ->where('node_id', $self)
                ->where('id', '>', $acknowledged)
                ->count();
        }

        return $pending;
    }

    private function outboundLastSyncedAt(string $peerId): ?string
    {
        $lastSyncedAt = SyncCursor::query()
            ->where('node_id', (string) config('sync.node_id'))
            ->where('target_node_id', $peerId)
            ->where('direction', 'out')
            ->value('last_synced_at');

        return $lastSyncedAt === null ? null : (string) $lastSyncedAt;
    }

    /**
     * Peer ids exactly as SyncCycleRunner derives them: a map entry is keyed
     * by node id, a bare-list entry is its own URL. Pending bookkeeping must
     * use the same ids as delivery or the counts point at nothing.
     *
     * @return list<string>
     */
    private function peerIds(): array
    {
        $ids = [];

        foreach (config('sync.peers', []) as $peerId => $peerConfig) {
            $ids[] = (string) (is_array($peerConfig) ? $peerId : $peerConfig);
        }

        return $ids;
    }

    private function peerSummary(): string
    {
        $peers = config('sync.peers', []);

        if ($peers === []) {
            return '(none)';
        }

        $parts = [];

        foreach ($peers as $peerId => $peerConfig) {
            $baseUrl = is_array($peerConfig) ? ($peerConfig['base_url'] ?? '') : $peerConfig;

            $parts[] = is_array($peerConfig) ? "{$peerId} => {$baseUrl}" : (string) $baseUrl;
        }

        return implode(', ', $parts);
    }
}
