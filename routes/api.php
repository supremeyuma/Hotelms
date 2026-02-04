<?php

use App\Http\Controllers\WebhookController;

Route::post('/webhooks/paystack', [WebhookController::class, 'paystack']);
Route::post('/webhooks/flutterwave', [WebhookController::class, 'flutterwave']);
