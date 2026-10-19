<?php

use App\Http\Controllers\Sync\ChangeLogController;
use App\Http\Controllers\Sync\NodeRegistrationController;
use App\Http\Controllers\WebhookController;

Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystackWebhook']);
Route::post('/webhooks/flutterwave', [WebhookController::class, 'handleFlutterwaveWebhook']);

// Pre-credential pairing exchange: the caller holds no node credential until
// this exchange succeeds, so it sits OUTSIDE the sync.node group and carries
// its own strict throttle - guessing a pairing code must be expensive. The
// codes themselves are single-use and time-limited (Phase 8, task 3).
Route::post('/sync/register', [NodeRegistrationController::class, 'store'])
    ->middleware('throttle:10,1');

Route::middleware(['sync.node'])->prefix('sync')->group(function () {
    Route::get('/changes', [ChangeLogController::class, 'index']);
    Route::post('/changes', [ChangeLogController::class, 'store']);
});
