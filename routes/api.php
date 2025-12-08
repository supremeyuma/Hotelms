<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoomApiController;
use App\Http\Controllers\Api\BookingApiController;

/*
|--------------------------------------------------------------------------
| API ROUTES
|--------------------------------------------------------------------------
|
| Used for mobile apps, kiosks, tablets, and integrations.
|
*/

// Room availability
Route::get('/rooms/{room}/availability', [RoomApiController::class, 'availability'])
    ->name('api.rooms.availability');

// Booking availability check
Route::post('/booking/check', [BookingApiController::class, 'checkAvailability'])
    ->name('api.booking.check');

// Example: Mobile booking endpoint
Route::post('/booking', [BookingApiController::class, 'store'])
    ->name('api.booking.store');
