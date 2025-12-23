<?php

use App\Http\Controllers\Guest\RoomDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('guest')->group(function () {
    Route::middleware(['validate.room.token'])->group(function () {
        Route::get('/room/{token}', [RoomDashboardController::class, 'index'])->name('guest.room.dashboard');
        // Future guest actions
        Route::post('/room/{token}/service-request', [RoomDashboardController::class, 'serviceRequest']);
        Route::post('/room/{token}/maintenance', [RoomDashboardController::class, 'reportMaintenance']);
        Route::post('/room/{token}/extend-stay', [RoomDashboardController::class, 'extendStay']);
        Route::post('/room/{token}/checkout', [RoomDashboardController::class, 'checkout']);
        Route::post('/room/{token}/payment', [RoomDashboardController::class, 'payBill']);
    });
});
