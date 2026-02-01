<?php

/**
 * Payment Gateway Configuration
 * 
 * Control which payment providers are enabled and their settings.
 * When multiple providers are enabled, customers can choose.
 * When only one is enabled, it's used automatically.
 */

return [
    /**
     * Enabled Payment Providers
     * 
     * Set to true to enable a provider, false to disable
     */
    'providers' => [
        'flutterwave' => env('PAYMENT_FLUTTERWAVE_ENABLED', true),
        'paystack' => env('PAYMENT_PAYSTACK_ENABLED', false),
    ],

    /**
     * Flutterwave Settings
     */
    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'timeout' => env('FLUTTERWAVE_TIMEOUT', 30),
    ],

    /**
     * Paystack Settings
     */
    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'timeout' => env('PAYSTACK_TIMEOUT', 30),
    ],

    /**
     * Payment Method Display Names
     */
    'provider_labels' => [
        'flutterwave' => 'Flutterwave',
        'paystack' => 'Paystack',
    ],

    /**
     * Default Payment Provider (used when only one is enabled)
     */
    'default' => env('PAYMENT_DEFAULT_PROVIDER', 'flutterwave'),

    /**
     * Webhook Settings
     */
    'webhooks' => [
        'timeout' => env('PAYMENT_WEBHOOK_TIMEOUT', 60),
        'retry_count' => env('PAYMENT_WEBHOOK_RETRY_COUNT', 3),
    ],

    /**
     * Transaction Fees (in percentage)
     */
    'fees' => [
        'flutterwave' => env('FLUTTERWAVE_TRANSACTION_FEE', 1.4),
        'paystack' => env('PAYSTACK_TRANSACTION_FEE', 1.5),
    ],

    /**
     * Logging
     */
    'logging' => [
        'enabled' => env('PAYMENT_LOGGING_ENABLED', true),
        'channel' => env('PAYMENT_LOG_CHANNEL', 'single'),
    ],
];
