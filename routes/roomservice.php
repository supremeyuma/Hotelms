<?php

use App\Http\Controllers\Guest\RoomDashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GuestLaundryController;
use App\Http\Controllers\Guest\MenuController;
use App\Http\Controllers\Guest\OrderController;
use App\Http\Controllers\Guest\OrderHistoryController;

Route::prefix('guest')->group(function () {
    Route::middleware(['validate.room.token'])->group(function () {
        Route::get('/room/{token}', [RoomDashboardController::class, 'index'])->name('guest.room.dashboard');
        // Future guest actions
        Route::post('/room/{token}/service-request', [RoomDashboardController::class, 'serviceRequest']);
        Route::post('/room/{token}/maintenance', [RoomDashboardController::class, 'reportMaintenance']);
        Route::post('/room/{token}/extend-stay', [RoomDashboardController::class, 'extendStay']);
        Route::post('/room/{token}/checkout', [RoomDashboardController::class, 'checkout']);
        Route::post('/room/{token}/payment', [RoomDashboardController::class, 'payBill']);
        Route::get('/room/{token}/bill-history', [RoomDashboardController::class, 'billHistory'])->name('guest.room.bill-history');
    });
});

Route::middleware(['auth', 'resolve.guest.room'])->group(function () {
    Route::get('/guest/room/{token}/laundry', [GuestLaundryController::class, 'show'])->name('guest.laundry.show');
    Route::post('/guest/room/{token}/laundry', [GuestLaundryController::class, 'store'])->name('guest.laundry.store');
});

Route::prefix('guest/room/{token}')->group(function () {
    Route::get('/menu/{type}', [MenuController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->name('guest.orders.store');
    Route::get('/orders', [OrderHistoryController::class, 'index'])->name('guest.orders.index');
    Route::post('/orders/{order:id}/cancel', [OrderController::class, 'cancel'])->name('guest.orders.cancel');
});


