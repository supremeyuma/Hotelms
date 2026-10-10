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
];