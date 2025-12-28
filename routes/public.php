<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RoomServiceController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\GuestLaundryController;

/*
|--------------------------------------------------------------------------
| Public Only Routes
|--------------------------------------------------------------------------
|
| Public-facing endpoints used by the landing pages and QR devices.
|
*/

Route::get('/', [PublicController::class, 'homepage'])->name('public.home');
Route::get('/rooms', [PublicController::class, 'showRoomTypes'])->name('public.rooms.index');
Route::get('/rooms/{room}', [PublicController::class, 'showRoom'])->name('public.rooms.show');

Route::get('/gallery', [PublicController::class, 'staticPage'])->defaults('pageKey','gallery')->name('public.gallery');
Route::get('/contact', [PublicController::class, 'staticPage'])->defaults('pageKey','contact')->name('public.contact');
Route::post('/contact', [PublicController::class, 'submitContactForm'])->name('public.contact.submit');

// Public booking endpoints
//Route::post('/bookings/check-availability', [BookingController::class, 'checkAvailability'])->name('public.booking.check');

// Room service public (signed) - QR links should be signed URLs in mails/printouts
Route::get('/room/{room}/service', [RoomServiceController::class, 'showMenuForRoom'])
    ->name('public.room.service')
    ->middleware('signed');




Route::prefix('booking')->name('booking.')->group(function () {
    Route::get('/', [BookingController::class, 'searchForm'])->name('search');
    Route::get('/rooms', [BookingController::class, 'availableRooms'])->name('rooms');
    Route::post('/select-room', [BookingController::class, 'selectRoom'])->name('selectRoom');
    Route::get('/guest', [BookingController::class, 'guestDetails'])->name('guest');
    Route::post('/guest', [BookingController::class, 'submitGuestDetails'])->name('submitGuest');
    Route::get('/review', [BookingController::class, 'reviewBooking'])->name('review');
    Route::post('/create', [BookingController::class, 'createBooking'])->name('create');
    Route::get('/payment/{booking}', [BookingController::class, 'payment'])->name('payment');
    Route::post('/payment/{booking}/confirm', [BookingController::class, 'confirmPayment'])->name('payment.confirm');
    Route::get('/confirmation/{booking}', [BookingController::class, 'confirmation'])->name('confirmation');
});

Route::middleware(['auth', 'resolve.guest.room'])->group(function () {
    Route::get('/guest/room/{token}/laundry', [GuestLaundryController::class, 'show'])->name('guest.laundry.show');
    Route::post('/guest/room/{token}/laundry', [GuestLaundryController::class, 'store'])->name('guest.laundry.store');
});