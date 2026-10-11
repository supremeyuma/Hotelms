<?php

namespace App\Sync;

use App\Sync\Exceptions\SyncTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * HTTP transport for node-to-node change exchange.
 *
 * Batches, because a terminal may have thousands of changes after a long
 * offline stretch and a per-row request loop would make a restore take hours.
 *
 * $peer is always a base URL. Peer identity (the node id used for cursor
 * bookkeeping) is resolved one layer up by NodeSyncService; the transport only
 * needs somewhere to dial and the credential to present there.
 */
class HttpSyncTransport
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * Hand a batch of changes to a peer.
     *
     * @param  array<int, array<string, mixed>>  $changes
     * @return array{accepted: int}
     */
    public function push(string $peer, array $changes): array
    {
        $response = $this->send($peer, 'POST', '/api/sync/changes', ['changes' => $changes]);

        return ['accepted' => (int) ($response['accepted'] ?? 0)];
    }

    /**
     * Pull changes a peer holds past our cursor.
     *
     * @return array{changes: array<int, array<string, mixed>>, cursor: int, has_more: bool}
     */
    public function pull(string $peer, int $sinceId, int $limit = 500): array
    {
        $response = $this->send($peer, 'GET', '/api/sync/changes', [
            'since' => $sinceId,
            'limit' => $limit,
        ]);

        return [
            'changes'  => $response['changes'] ?? [],
            'cursor'   => (int) ($response['cursor'] ?? $sinceId),
            'has_more' => (bool) ($response['has_more'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $peer, string $method, string $path, array $payload): array
    {
        try {
            $request = $this->http->timeout(config('sync.request_timeout'))
                ->acceptJson()
                ->withHeaders([
                    'X-Sync-Node-Id' => (string) config('sync.node_id'),
                    'Authorization'  => 'Bearer ' . $this->credentialFor($peer),
                ]);

            $response = $method === 'POST'
                ? $request->post(rtrim($peer, '/') . $path, $payload)
                : $request->get(rtrim($peer, '/') . $path, $payload);

            if ($response->failed()) {
                throw new SyncTransportException(
                    "Sync {$method} {$peer}{$path} failed: {$response->status()} {$response->body()}",
                );
            }

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            throw new SyncTransportException("Could not reach peer [{$peer}]: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * The secret this node presents to a peer.
     *
     * A node holds one identity secret, so a single `sync.credential` covers
     * every peer. Per-peer overrides exist only for the unusual case of one
     * process presenting different identities to different peers, and are read
     * from either the legacy `sync.credentials` map (keyed by base URL) or a
     * peer entry's own `credential`.
     */
    private function credentialFor(string $peer): string
    {
        $peer = rtrim($peer, '/');

        $credentials = config('sync.credentials', []);

        if (isset($credentials[$peer])) {
            return (string) $credentials[$peer];
        }

        foreach (config('sync.peers', []) as $peerConfig) {
            $baseUrl = is_array($peerConfig) ? ($peerConfig['base_url'] ?? null) : $peerConfig;

            if ($baseUrl === null || rtrim((string) $baseUrl, '/') !== $peer) {
                continue;
            }

            if (is_array($peerConfig) && ! empty($peerConfig['credential'])) {
                return (string) $peerConfig['credential'];
            }
        }

        return (string) config('sync.credential', '');
    }
}
