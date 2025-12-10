<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffActionController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffProfileController;

/*
|--------------------------------------------------------------------------
| Staff Routes
|--------------------------------------------------------------------------
|
| Routes used by authenticated staff. Protected by auth + role middleware.
|
*/

Route::middleware(['auth', 'role:staff|manager|md'])->prefix('staff')->name('staff.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');

    // Quick action (verify staff action codes)
    Route::get('/quick-action', [StaffActionController::class, 'index'])->name('quick-action.index');
    Route::post('/quick-action/verify', [StaffActionController::class, 'recordAction'])->name('quick-action.verify');

    // Orders queue (department specific controllers/policies will enforce)
    Route::get('/orders', [StaffOrderController::class, 'listOrders'])->name('orders.queue');
    Route::patch('/orders/{order}/status', [StaffOrderController::class, 'updateOrderStatus'])->name('orders.updateStatus');

    // Specialized staff actions
    Route::post('/orders/{order}/pickup-laundry', [StaffOrderController::class, 'pickupLaundry'])->name('orders.pickupLaundry');
    Route::post('/orders/{order}/deliver-laundry', [StaffOrderController::class, 'deliverLaundry'])->name('orders.deliverLaundry');
    Route::post('/orders/{order}/kitchen-ready', [StaffOrderController::class, 'kitchenOrderReady'])->name('orders.kitchenReady');

    // Profile
    Route::get('/profile', [StaffProfileController::class, 'showProfile'])->name('profile.show');
    Route::patch('/profile', [StaffProfileController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/action-code', [StaffProfileController::class, 'updateActionCode'])->name('profile.action_code');
});
