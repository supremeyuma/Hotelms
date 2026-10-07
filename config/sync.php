<?php

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
    | Peers
    |--------------------------------------------------------------------------
    |
    | Comma-separated base URLs. Per spec section 4.3: a terminal prefers
    | the LAN server and falls back to the cloud, so a dead LAN server
    | degrades throughput rather than stopping reconciliation.
    |
    */

    'peers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SYNC_PEERS', ''))
    ))),

    'batch_size' => (int) env('SYNC_BATCH_SIZE', 500),

    'request_timeout' => (int) env('SYNC_REQUEST_TIMEOUT', 30),
];
