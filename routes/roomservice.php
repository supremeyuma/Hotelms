<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomServiceController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| Room Service Routes (roomservice.php)
|--------------------------------------------------------------------------
|
| Routes for placing and tracking room service orders, and posting requests
| from kiosk/QR devices. Signed URLs recommended for room-specific access.
|
*/

Route::prefix('room')->group(function () {

    // QR entry / menu display (signed)
    Route::get('{room}/service', [RoomServiceController::class, 'showMenuForRoom'])
        ->name('room.service.show')
        ->middleware('signed');

    // Place order for a given room (public or authenticated)
    Route::post('{room}/service/order', [RoomServiceController::class, 'placeOrder'])
        ->name('room.service.placeOrder');

    // General roomservice endpoints (non-room specific)
    Route::post('/order', [OrderController::class, 'store'])->name('roomservice.order.store');
    Route::get('/track/{order_code}', [RoomServiceController::class, 'trackOrder'])->name('roomservice.track');
});
