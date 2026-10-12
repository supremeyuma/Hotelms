<?php

namespace App\Services\Connectivity;

use App\Sync\Exceptions\SyncTransportException;
use App\Sync\SyncCycleRunner;

/**
 * Answers "can this terminal currently reach a peer", from evidence rather
 * than from the network stack.
 *
 * A cable being plugged in proves nothing. A terminal that can reach a server
 * which returns 500 is not online, and a sale must not depend on it.
 */
class ConnectivityMonitor
{
    private ?array $lastResult = null;

    private bool $probed = false;

    public function __construct(private readonly SyncCycleRunner $runner) {}

    /**
     * @return array{online: bool, checked_at: ?string, peers: int, failed: list<string>}
     */
    public function status(bool $probe = false): array
    {
        if (! config('sync.enabled')) {
            // Sync disabled means a single-node deployment. Always available.
            return ['online' => true, 'checked_at' => null, 'peers' => 0, 'failed' => []];
        }

        if ($probe || ! $this->probed) {
            $this->lastResult = $this->probe();
            $this->probed = true;
        }

        $result = $this->lastResult ?? ['peers' => 0, 'failed' => []];

        return [
            'online' => $this->isOnline($result),
            'checked_at' => $this->lastResult === null ? null : now()->toIso8601String(),
            'peers' => (int) $result['peers'],
            'failed' => $result['failed'],
        ];
    }

    /**
     * Any reachable peer is enough: per spec section 4.3 a terminal prefers the
     * LAN server and falls back to the cloud, so one dead peer degrades
     * throughput rather than making the terminal offline.
     *
     * @param  array{peers: int, failed: list<string>}  $result
     */
    private function isOnline(array $result): bool
    {
        return $result['peers'] > 0 && count($result['failed']) < $result['peers'];
    }

    /**
     * Run one real sync cycle. SyncCycleRunner already isolates per-peer
     * failures, so its result carries both the configured peer count and the
     * peers that did not answer.
     *
     * @return array{peers: int, failed: list<string>}
     */
    private function probe(): array
    {
        try {
            $result = $this->runner->run();

            return ['peers' => $result['peers'], 'failed' => $result['failed']];
        } catch (SyncTransportException $e) {
            return ['peers' => 0, 'failed' => [$e->getMessage()]];
        }
    }
}
