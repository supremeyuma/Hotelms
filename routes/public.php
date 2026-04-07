<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\Public\PublicEventController;
use App\Http\Controllers\Public\PublicMenuOnlineController;
use App\Http\Controllers\Public\PublicMenuViewOnlyController;
use App\Http\Controllers\Public\PublicOrderController;
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

Route::get('/', [PublicController::class,'home']);
Route::get('/gallery', [PublicController::class,'gallery']);
Route::get('/amenities', [PublicController::class,'amenities']);
Route::get('/club-lounge', [PublicController::class,'club']);
Route::get('/policies', [PublicController::class,'policies']);

Route::get('/contact', [PublicController::class, 'staticPage'])->defaults('pageKey','contact')->name('public.contact');
Route::post('/contact', [PublicController::class, 'submitContactForm'])->name('public.contact.submit');

// Public booking endpoints
//Route::post('/bookings/check-availability', [BookingController::class, 'checkAvailability'])->name('public.booking.check');

// Event routes
Route::get('/events', [PublicEventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [PublicEventController::class, 'show'])->name('events.show');
Route::get('/events/{event}/tickets', [PublicEventController::class, 'showTicketPurchase'])->name('events.tickets');
Route::post('/events/{event}/tickets/purchase', [PublicEventController::class, 'processTicketPurchase'])->name('events.tickets.purchase');
Route::get('/events/purchase/success', [PublicEventController::class, 'purchaseSuccess'])->name('events.purchase.success');
Route::get('/events/{event}/reserve-table', [PublicEventController::class, 'showTableReservation'])->name('events.tables.reserve');
Route::post('/events/{event}/tables/reserve', [PublicEventController::class, 'processTableReservation'])->name('events.tables.reserve');
Route::get('/events/reservation/success', [PublicEventController::class, 'reservationSuccess'])->name('events.reservation.success');
Route::get('/events/payment/process', [PublicEventController::class, 'paymentProcess'])->name('events.payment.process');
Route::get('/events/payment/callback', [PublicEventController::class, 'paymentCallback'])->name('events.payment.callback');
Route::get('/events/payment/failed', [PublicEventController::class, 'paymentFailed'])->name('events.payment.failed');
Route::get('/events/checkin', [PublicEventController::class, 'checkIn'])->name('events.checkin');

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
    Route::get('/payment/{booking}/callback', [BookingController::class, 'paymentCallback'])->name('payment.callback');
    Route::get('/confirmation/{booking}', [BookingController::class, 'confirmation'])->name('confirmation');
    Route::get('/pre-check-in/{booking}', [BookingController::class, 'preCheckIn'])->middleware('signed')->name('precheck.show');
    Route::post('/pre-check-in/{booking}', [BookingController::class, 'submitPreCheckIn'])->middleware('signed')->name('precheck.submit');
});

// Public menu routes - Online ordering (prepaid)
Route::prefix('menu/online')->name('menu.online.')->group(function () {
    Route::get('/{type?}', [PublicMenuOnlineController::class, 'index'])->name('show');
});

// Public menu routes - View only (walk-in)
Route::prefix('menu/view')->name('menu.view.')->group(function () {
    Route::get('/{type?}', [PublicMenuViewOnlyController::class, 'index'])->name('show');
});

// Public order endpoint
Route::post('/public/orders', [PublicOrderController::class, 'store'])->name('public.orders.store');

