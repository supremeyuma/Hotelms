<?php

use App\Http\Controllers\Sync\ChangeLogController;
use App\Http\Controllers\WebhookController;

Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystackWebhook']);
Route::post('/webhooks/flutterwave', [WebhookController::class, 'handleFlutterwaveWebhook']);

Route::middleware(['sync.node'])->prefix('sync')->group(function () {
    Route::get('/changes', [ChangeLogController::class, 'index']);
    Route::post('/changes', [ChangeLogController::class, 'store']);
});
