// File: routes/api.php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomApiController;
use App\Http\Controllers\Api\BookingApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\StaffApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Minimal API surface for mobile/kiosk integrations and dynamic frontend needs.
| These routes are stateless and should use api middleware and token authentication.
|
*/

Route::middleware('auth:sanctum')->group(function () {
    // Room availability check
    Route::get('/rooms/{room}/availability', [RoomApiController::class, 'availability'])->name('api.rooms.availability');

    // Create booking from mobile kiosk
    Route::post('/booking/check', [BookingApiController::class, 'checkAvailability'])->name('api.booking.check');
    Route::post('/booking', [BookingApiController::class, 'store'])->name('api.booking.store');

    // Order APIs
    Route::post('/orders', [OrderApiController::class, 'store'])->name('api.orders.store');
    Route::patch('/orders/{order}/status', [OrderApiController::class, 'updateStatus'])->name('api.orders.status');

    // Staff actions (record action code attempts)
    Route::post('/staff/action', [StaffApiController::class, 'recordAction'])->name('api.staff.action');

    // Notifications polling
    Route::get('/notifications', [StaffApiController::class, 'notifications'])->name('api.notifications');
});

// Public API endpoints (no auth)
Route::get('/rooms', [RoomApiController::class, 'index'])->name('api.rooms.index');
Route::get('/rooms/{room}', [RoomApiController::class, 'show'])->name('api.rooms.show');
