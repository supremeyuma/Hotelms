<?php

return [
    'devices' => [
        'auto_register' => (bool) env('POS_AUTO_REGISTER_DEVICES', true),
        'identity_cookie' => env('POS_DEVICE_IDENTITY_COOKIE', 'pos_device_code'),
        'default' => [
            'code' => env('POS_DEFAULT_DEVICE_CODE', 'POS-001'),
            'name' => env('POS_DEFAULT_DEVICE_NAME', 'Club POS Terminal'),
            'type' => 'club_terminal',
        ],
    ],
    'print' => [
        // The local print bridge daemon. Loopback only — it owns the physical
        // printer and must never be reachable from the LAN.
        'bridge_url' => env('POS_PRINT_BRIDGE_URL', 'http://127.0.0.1:9100'),
        // Paper profile in characters: 32 for 58mm, 48 for 80mm.
        'paper_width' => (int) env('POS_PRINT_PAPER_WIDTH', 32),
    ],
];
