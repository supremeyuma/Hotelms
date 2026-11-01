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

    'product' => env('LICENCE_PRODUCT', 'MooreLife'),
];
