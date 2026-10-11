<?php

/*
| Peer configuration is a map of peer node id to its base URL, so the id -
| not the address - is the stable identity a cursor tracks. Repointing a
| peer at a new address keeps its delivery history.
|
| SYNC_PEERS is JSON: {"node-b":{"base_url":"http://10.0.0.2:8000"}}
| A peer entry may carry its own "credential" when one process presents a
| different identity to different peers; otherwise the node credential is used.
|
| A bare comma-separated list of URLs is still accepted and keyed by the URL
| itself, so an older `SYNC_PEERS=<lan>,<cloud>` deployment does not break.
*/
$syncPeers = (function (): array {
    $raw = trim((string) env('SYNC_PEERS', ''));

    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (is_array($decoded) && ! array_is_list($decoded)) {
        $peers = [];

        foreach ($decoded as $id => $entry) {
            $entry = is_string($entry) ? ['base_url' => $entry] : $entry;

            if (! is_array($entry) || empty($entry['base_url'])) {
                continue;
            }

            $peers[(string) $id] = [
                'base_url'   => rtrim((string) $entry['base_url'], '/'),
                'credential' => isset($entry['credential']) ? (string) $entry['credential'] : null,
            ];
        }

        return $peers;
    }

    $peers = [];

    foreach (array_filter(array_map('trim', explode(',', $raw))) as $url) {
        $url = rtrim($url, '/');

        $peers[$url] = ['base_url' => $url, 'credential' => null];
    }

    return $peers;
})();

return [
    /*
    |--------------------------------------------------------------------------
    | Sync Enablement
    |--------------------------------------------------------------------------
    |
    | Off by default. An unconfigured deployment behaves exactly as it did
    | before sync existed. Spec section 10: synchronization is purely
    | additive and must not change existing behaviour.
    |
    */

    'enabled' => (bool) env('SYNC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Node Identity
    |--------------------------------------------------------------------------
    |
    | A ULID minted at first setup and persisted thereafter. It must be
    | stable: changing it orphans every row this node has written and
    | breaks the last-write-wins tie-break.
    |
    */

    'node_id' => env('SYNC_NODE_ID'),

    'node_type' => env('SYNC_NODE_TYPE', 'terminal'),

    'property_id' => env('SYNC_PROPERTY_ID'),

    /*
    |--------------------------------------------------------------------------
    | Credential
    |--------------------------------------------------------------------------
    |
    | The secret this node presents to every peer. The peer stores only its
    | SHA-256 hash, so a database leak does not yield a usable credential.
    |
    */

    'credential' => env('SYNC_CREDENTIAL'),

    /*
    |--------------------------------------------------------------------------
    | Peers
    |--------------------------------------------------------------------------
    |
    | Map of peer node id => {base_url, credential}. Ordered by preference:
    | per spec section 4.3 a terminal prefers the LAN server and falls back to
    | the cloud, so a dead LAN server degrades throughput rather than stopping
    | reconciliation.
    |
    */

    'peers' => $syncPeers,

    /*
    | Per-peer credential overrides keyed by base URL. Normally empty; the
    | node credential is presented to every peer.
    */

    'credentials' => [],

    'batch_size' => (int) env('SYNC_BATCH_SIZE', 500),

    'request_timeout' => (int) env('SYNC_REQUEST_TIMEOUT', 30),
];
