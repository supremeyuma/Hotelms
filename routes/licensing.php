<?php

use App\Http\Controllers\LicenceCheckoutController;
use App\Http\Controllers\LicenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Licensing
|--------------------------------------------------------------------------
|
| Activation is called by the desktop app, not by a browser: the customer pastes
| the key they bought, the app sends it with its machine id, and receives a
| signed licence file it verifies offline. Throttled because keys are short
| enough to guess.
|
| The rest is the platform's own shop. It only exists on the licence server
| (LICENCE_CHECKOUT_ENABLED, off by default) and is deliberately outside the
| hotel's public site and layout: a guest must never be sold a MooreLife licence.
|
*/

Route::post('/licence/activate', [LicenceController::class, 'activate'])
    ->middleware('throttle:10,1')
    ->name('licence.activate');

Route::get('/pricing', [LicenceCheckoutController::class, 'pricing'])
    ->name('licence.pricing');

Route::get('/licence/checkout', [LicenceCheckoutController::class, 'create'])
    ->name('licence.checkout.create');

Route::post('/licence/checkout', [LicenceCheckoutController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('licence.checkout.store');

// The gateway redirects here with its own transaction reference appended.
Route::get('/licence/checkout/{order}/callback', [LicenceCheckoutController::class, 'callback'])
    ->whereNumber('order')
    ->name('licence.checkout.callback');

// Signed: the key is revealed here once, so the URL must not be guessable.
Route::get('/licence/checkout/{order}/success', [LicenceCheckoutController::class, 'success'])
    ->whereNumber('order')
    ->middleware('signed')
    ->name('licence.checkout.success');

Route::get('/licence/checkout/failed', [LicenceCheckoutController::class, 'failed'])
    ->name('licence.checkout.failed');
