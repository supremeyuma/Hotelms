<?php

use App\Http\Controllers\WebhookController;

Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystackWebhook']);
Route::post('/webhooks/flutterwave', [WebhookController::class, 'flutterwave']);
