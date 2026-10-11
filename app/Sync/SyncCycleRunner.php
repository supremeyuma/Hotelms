<?php

namespace App\Sync;

use App\Sync\Exceptions\SyncTransportException;
use Illuminate\Support\Facades\Log;

/**
 * Run a synchronization cycle against every configured peer.
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
     * Push to and pull from every configured peer.
     *
     * @return array{peers: int, pushed: int, pulled: int, failed: list<string>}
     */
    public function run(?string $onlyPeer = null): array
    {
        $pushed = 0;
        $pulled = 0;
        $failed = [];

        $peers = $this->resolvePeers($onlyPeer);

        foreach ($peers as $peerId => $baseUrl) {
            try {
                $result = $this->sync->syncWith($peerId, $baseUrl);

                $pushed += $result['pushed'];
                $pulled += $result['pulled'];
            } catch (SyncTransportException $e) {
                $failed[] = $this->logFailure($peerId, $e);
            }
        }

        return [
            'peers'  => count($peers),
            'pushed' => $pushed,
            'pulled' => $pulled,
            'failed' => $failed,
        ];
    }

    /**
     * Push only: send our changes to each peer.
     *
     * @return array{peers: int, pushed: int, pulled: int, failed: list<string>}
     */
    public function push(?string $onlyPeer = null): array
    {
        return $this->each($onlyPeer, fn (string $id, string $url): array => [
            'pushed' => $this->sync->pushOnly($id, $url),
            'pulled' => 0,
        ]);
    }

    /**
     * Pull only: fetch and apply each peer's changes.
     *
     * @return array{peers: int, pushed: int, pulled: int, failed: list<string>}
     */
    public function pull(?string $onlyPeer = null): array
    {
        return $this->each($onlyPeer, fn (string $id, string $url): array => [
            'pushed' => 0,
            'pulled' => $this->sync->pullOnly($id, $url)[0],
        ]);
    }

    /**
     * @param  callable(string, string): array{pushed: int, pulled: int}  $operation
     * @return array{peers: int, pushed: int, pulled: int, failed: list<string>}
     */
    private function each(?string $onlyPeer, callable $operation): array
    {
        $peers = $this->resolvePeers($onlyPeer);
        $pushed = 0;
        $pulled = 0;
        $failed = [];

        foreach ($peers as $peerId => $baseUrl) {
            try {
                $result = $operation($peerId, $baseUrl);

                $pushed += $result['pushed'];
                $pulled += $result['pulled'];
            } catch (SyncTransportException $e) {
                $failed[] = $this->logFailure($peerId, $e);
            }
        }

        return [
            'peers'  => count($peers),
            'pushed' => $pushed,
            'pulled' => $pulled,
            'failed' => $failed,
        ];
    }

    /**
     * A peer list may be a map of id => {base_url} or a bare list of URLs.
     * Either way the id is the cursor key; when there is no id the URL is the id.
     *
     * @return array<string, string>
     */
    private function resolvePeers(?string $onlyPeer): array
    {
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

            if ($onlyPeer !== null && $onlyPeer !== $peerId) {
                continue;
            }

            $resolved[$peerId] = $baseUrl;
        }

        return $resolved;
    }

    private function logFailure(string $peerId, SyncTransportException $e): string
    {
        Log::warning('Sync peer unreachable.', [
            'peer'  => $peerId,
            'error' => $e->getMessage(),
        ]);

        return $peerId;
    }
}
