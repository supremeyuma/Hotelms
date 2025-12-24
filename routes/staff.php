<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffActionController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffProfileController;
use App\Http\Controllers\FrontDesk\DashboardController;
use App\Http\Controllers\FrontDesk\BookingController;
use App\Http\Controllers\FrontDesk\RoomController;
use App\Http\Controllers\FrontDesk\BillingController;
use App\Http\Controllers\FrontDesk\RoomBillingController;


/*
|--------------------------------------------------------------------------
| Staff Routes
|--------------------------------------------------------------------------
|
| Routes used by authenticated staff. Protected by auth + role middleware.
|
*/

Route::middleware(['auth', 'role:Staff|manager|md'])->prefix('staff')->name('staff.')->group(function () {

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

    Route::patch('/staff/frontdesk/checkout/{booking}', [FrontDeskController::class, 'checkout'])->name('staff.frontdesk.checkout');

});


Route::prefix('frontdesk')->middleware(['auth', 'role:frontdesk'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('frontdesk.dashboard');

    Route::get('/rooms/{room}/billing', [RoomBillingController::class, 'show']);
    Route::post('/rooms/{room}/billing/pay', [RoomBillingController::class, 'pay']);

    Route::resource('bookings', BookingController::class);
    Route::post('bookings/{booking}/checkin', [BookingController::class, 'checkIn']);
    Route::post('bookings/{booking}/checkout', [BookingController::class, 'checkOut']);
    Route::post('bookings/{booking}/extend', [BookingController::class, 'extendStay']);

    Route::resource('rooms', RoomController::class)->only(['index', 'show', 'updateStatus']);

    Route::get('guest-requests', [GuestRequestController::class, 'index']);
    Route::post('guest-requests/{request}/acknowledge', [GuestRequestController::class, 'acknowledge']);
    Route::post('guest-requests/{request}/complete', [GuestRequestController::class, 'complete']);

    Route::get('billing/{booking}', [BillingController::class, 'viewBill']);
    Route::post('billing/{booking}/pay', [BillingController::class, 'acceptPayment']);

    Route::get('reports/occupancy', [ReportController::class, 'occupancyReport']);
    Route::get('reports/revenue', [ReportController::class, 'revenueReport']);
    Route::get('reports/bookings', [ReportController::class, 'bookingHistoryReport']);
});




