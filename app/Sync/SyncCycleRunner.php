<?php

namespace App\Sync;

use App\Sync\Exceptions\SyncTransportException;
use Illuminate\Support\Facades\Log;

/**
 * Run one synchronization cycle against every configured peer.
 *
 * A dead peer is logged and skipped. Reconciliation continues against the
 * remaining peers, because a terminal must always have somewhere to sync to
 * even when its LAN server is unreachable (spec section 4.3).
 */
class SyncCycleRunner
{
    public function __construct(
        private readonly NodeSyncService $sync,
    ) {
    }

    /**
     * @param  string|null  $onlyPeer  Restrict the cycle to a single peer id.
     * @return array{peers: int, pushed: int, pulled: int, failed: list<string>}
     */
    public function run(?string $onlyPeer = null): array
    {
        $peers = config('sync.peers', []);
        $pushed = 0;
        $pulled = 0;
        $failed = [];

        foreach ($peers as $peerId => $peerConfig) {
            // A peer list may be a map of id => {base_url} or a bare list of
            // URLs. Either way the id is the cursor key; when there is no id
            // the URL is the id.
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

            if ($onlyPeer !== null && $onlyPeer !== $peerId) {
                continue;
            }

            try {
                $result = $this->sync->syncWith($peerId, $baseUrl);

                $pushed += $result['pushed'];
                $pulled += $result['pulled'];
            } catch (SyncTransportException $e) {
                Log::warning('Sync peer unreachable.', [
                    'peer'  => $peerId,
                    'error' => $e->getMessage(),
                ]);

                $failed[] = $peerId;
            }
        }

        return [
            'peers'  => count($peers),
            'pushed' => $pushed,
            'pulled' => $pulled,
            'failed' => $failed,
        ];
    }
}
