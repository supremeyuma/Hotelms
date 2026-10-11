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
        $this->table(
            ['Setting', 'Value'],
            [
                ['enabled',    (string) var_export(config('sync.enabled'), true)],
                ['node_id',    (string) config('sync.node_id')],
                ['node_type',  (string) config('sync.node_type')],
                ['peers',      $this->peerSummary()],
                ['changes',    (string) SyncChange::count()],
                ['registered', (string) SyncNode::count()],
            ],
        );

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
