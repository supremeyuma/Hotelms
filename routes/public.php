<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RoomServiceController;

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
Route::post('/bookings/check-availability', [BookingController::class, 'checkAvailability'])->name('public.booking.check');

// Room service public (signed) - QR links should be signed URLs in mails/printouts
Route::get('/room/{room}/service', [RoomServiceController::class, 'showMenuForRoom'])
    ->name('public.room.service')
    ->middleware('signed');
