<?php

use App\Http\Controllers\Guest\RoomEntryController;
use App\Http\Controllers\Guest\RoomDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GuestLaundryController;
use App\Http\Controllers\Guest\MenuController;
use App\Http\Controllers\Guest\OrderController;
use App\Http\Controllers\Guest\OrderHistoryController;
use App\Http\Controllers\FeedbackController;

Route::get('/guest/room-entry/{room:qr_key}', [RoomEntryController::class, 'show'])->name('guest.room.entry');
Route::get('/guest/room-payment/{room}/{reference}', [RoomDashboardController::class, 'handleBillPaymentCallback'])
    ->name('guest.bill.payment.callback');

Route::prefix('guest/room/{token}')->middleware(['validate.room.token'])->group(function () {
    Route::get('/', [RoomDashboardController::class, 'index'])->name('guest.room.dashboard');
    Route::get('/feedback', [FeedbackController::class, 'createGuest'])->name('guest.feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'storeGuest'])->name('guest.feedback.store');
    Route::post('/service-request', [RoomDashboardController::class, 'serviceRequest']);
    Route::post('/maintenance', [RoomDashboardController::class, 'reportMaintenance']);
    Route::post('/extend-stay', [RoomDashboardController::class, 'extendStay']);
    Route::post('/checkout', [RoomDashboardController::class, 'checkout']);
    Route::post('/payment', [RoomDashboardController::class, 'payBill']);
    Route::get('/bill-history', [RoomDashboardController::class, 'billHistory'])->name('guest.room.bill-history');
    Route::get('/laundry', [GuestLaundryController::class, 'show'])->name('guest.laundry.show');
    Route::post('/laundry', [GuestLaundryController::class, 'store'])->name('guest.laundry.store');
    Route::get('/menu/{type}', [MenuController::class, 'index'])->whereIn('type', ['kitchen', 'bar']);
    Route::post('/orders', [OrderController::class, 'store'])->name('guest.orders.store');
    Route::get('/orders', [OrderHistoryController::class, 'index'])->name('guest.orders.index');
    Route::post('/orders/{order:id}/cancel', [OrderController::class, 'cancel'])->name('guest.orders.cancel');
});



