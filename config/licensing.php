<?php

return [
    /*
     * Base64-encoded PEM of the RSA private key used to sign licence files.
     *
     * This key belongs on the licence server that customers activate against —
     * NEVER in an installer and never in the desktop app's environment. The
     * desktop app only carries the public half (desktop/src-tauri/src/licence_public.pem).
     */
    'private_key' => env('LICENCE_PRIVATE_KEY'),

    // Trial length the desktop app enforces. Kept here so the activation
    // response and the public pricing copy cannot disagree.
    'trial_days' => (int) env('LICENCE_TRIAL_DAYS', 30),

    // Term of one paid licence, in months.
    'term_months' => (int) env('LICENCE_TERM_MONTHS', 12),

    /*
     * How many times a customer may move a licence to another PC themselves.
     *
     * A move never extends the term. Two covers a dead server and a planned
     * replacement within a year; beyond that support takes over (and can reset
     * the allowance from the licence desk).
     */
    'self_service_transfers' => (int) env('LICENCE_SELF_SERVICE_TRANSFERS', 2),

    'product' => env('LICENCE_PRODUCT', 'MooreLife'),

    /*
     * Public checkout.
     *
     * Off by default: it must not be possible to take money on a deployment whose
     * activation endpoint is not live yet. Enable it on the licence server once
     * LICENCE_PRIVATE_KEY, the gateway keys and the public domain are in place.
     */
    'checkout_enabled' => (bool) env('LICENCE_CHECKOUT_ENABLED', false),

    // Currency preselected on the checkout form; the buyer can switch.
    'currency_default' => env('LICENCE_CURRENCY_DEFAULT', 'NGN'),
];
