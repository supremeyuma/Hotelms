<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Public\PublicEventController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RoomServiceController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| Web Routes (combined entry)
|--------------------------------------------------------------------------
|
| This file wires together public, roomservice, staff and admin routes.
| For large apps we split into separate files (see routes/*.php).
|
*/


// Order placement (public or authenticated)
Route::post('/order', [OrderController::class, 'store'])->name('order.store');

// Fallback static pages (terms, privacy)
Route::get('/pages/{pageKey}', [PublicController::class, 'staticPage'])->name('pages.static');

// Payment initialization and verification (multi-provider)
Route::post('/payments/initialize-booking', [PaymentController::class, 'initializeBooking']);
Route::post('/payments/verify', [PaymentController::class, 'verify'])->name('payments.verify');
Route::post('/payments/initialize-by-reference', [PaymentController::class, 'initializeByReference'])
    ->name('payments.initialize.by.reference');

// Payment webhooks - multi-provider support
Route::post('/webhooks/flutterwave', [App\Http\Controllers\WebhookController::class, 'handleFlutterwaveWebhook'])
    ->name('webhooks.flutterwave')
    ->middleware(['web']);

Route::post('/webhooks/paystack', [App\Http\Controllers\WebhookController::class, 'handlePaystackWebhook'])
    ->name('webhooks.paystack')
    ->middleware(['web']);


require __DIR__ . '/admin.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/console.php';
require __DIR__ . '/images.php';
require __DIR__ . '/public.php';
require __DIR__ . '/roomservice.php';
require __DIR__ . '/staff.php';
//require __DIR__ . '/cleaning.php';