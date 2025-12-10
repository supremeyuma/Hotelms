<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RoomServiceController;

/*
|--------------------------------------------------------------------------
| Web Routes (combined entry)
|--------------------------------------------------------------------------
|
| This file wires together public, roomservice, staff and admin routes.
| For large apps we split into separate files (see routes/*.php).
|
*/

Route::get('/', [PublicController::class, 'homepage'])->name('home');

// Public pages
Route::get('/rooms', [PublicController::class, 'showRoomTypes'])->name('rooms.index');
Route::get('/rooms/{room}', [PublicController::class, 'showRoom'])->name('rooms.show');
Route::get('/contact', [PublicController::class, 'staticPage'])->defaults('pageKey', 'contact')->name('contact');
Route::post('/contact', [PublicController::class, 'submitContactForm'])->name('contact.submit');
Route::get('/gallery', [PublicController::class, 'staticPage'])->defaults('pageKey','gallery')->name('public.gallery');

// Booking public flows
Route::get('/booking/{room?}', [PublicController::class, 'showRoom'])->name('booking.form'); // optional room prefill
Route::post('/bookings', [BookingController::class, 'createBooking'])->name('booking.create');
Route::get('/booking/{booking}/confirm', [BookingController::class, 'confirmBooking'])->name('booking.confirm');

// Order placement (public or authenticated)
Route::post('/order', [OrderController::class, 'store'])->name('order.store');

// Order tracking (public)
Route::get('/order/track/{order_code}', [RoomServiceController::class, 'trackOrder'])->name('order.track');

// Room service QR entry (signed link recommended)
Route::get('/room/{room}/service', [RoomServiceController::class, 'showMenuForRoom'])
    ->name('room.service')
    ->middleware('signed')
    ;

// Department queues exposed to staff/controllers (controllers will guard)
Route::get('/orders/kitchen', [OrderController::class, 'kitchenQueue'])->name('orders.kitchen');
Route::get('/orders/laundry', [OrderController::class, 'laundryQueue'])->name('orders.laundry');
Route::get('/orders/housekeeping', [OrderController::class, 'housekeepingQueue'])->name('orders.housekeeping');
Route::get('/orders/maintenance', [OrderController::class, 'maintenanceQueue'])->name('orders.maintenance');

// Status update endpoint
Route::patch('/order/{order}/status', [OrderController::class, 'updateStatus'])->name('order.status.update');

// Fallback static pages (terms, privacy)
Route::get('/pages/{pageKey}', [PublicController::class, 'staticPage'])->name('pages.static');

require __DIR__ . '/admin.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/console.php';
require __DIR__ . '/images.php';
require __DIR__ . '/public.php';
require __DIR__ . '/roomservice.php';
require __DIR__ . '/staff.php';