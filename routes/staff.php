<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffActionController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffProfileController;
use App\Http\Controllers\FrontDesk\DashboardController;
use App\Http\Controllers\FrontDesk\BookingsController;
use App\Http\Controllers\FrontDesk\RoomController;
use App\Http\Controllers\FrontDesk\BillingController;
use App\Http\Controllers\FrontDesk\RoomBillingController;
use App\Http\Controllers\FrontDesk\GuestRequestController;
use App\Http\Controllers\FrontDesk\ReportController;
use App\Http\Controllers\Staff\LaundryStaffController;
use App\Http\Controllers\FrontDesk\FrontDeskLaundryController;
use App\Http\Controllers\Staff\LaundryItemController;
use App\Http\Controllers\Staff\FrontDeskController;
use App\Http\Controllers\Staff\ReceiptController;
use App\Http\Controllers\Staff\KitchenOrderController;
use App\Http\Controllers\Staff\BarOrderController;
use App\Http\Controllers\Staff\KitchenDashboardController;
use App\Http\Controllers\Staff\BarDashboardController;
use App\Http\Controllers\Staff\MenuCategoryController;
use App\Http\Controllers\Staff\MenuSubcategoryController;
use App\Http\Controllers\Staff\MenuItemController;




/*
|--------------------------------------------------------------------------
| Staff Routes
|--------------------------------------------------------------------------
|
| Routes used by authenticated staff. Protected by auth + role middleware.
|
*/



Route::middleware(['auth', 'role:staff|manager|md|laundry|frontdesk'])->prefix('staff')->name('staff.')->group(function () {

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


Route::prefix('frontdesk')->middleware(['auth', 'role:frontdesk'])->name('frontdesk.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/rooms/{room}/billing', [RoomBillingController::class, 'show']);
    Route::post('/rooms/{room}/billing/pay', [RoomBillingController::class, 'pay']);

    Route::resource('bookings', BookingsController::class);
    Route::post('bookings/{booking}/checkin', [BookingsController::class, 'checkIn']);
    Route::post('bookings/{booking}/checkout', [BookingsController::class, 'checkOut']);
    Route::post('bookings/{booking}/extend', [BookingsController::class, 'extendStay']);

    Route::resource('rooms', RoomController::class)->only(['index', 'show', 'updateStatus']);

    Route::get('guest-requests', [GuestRequestController::class, 'index'])->name('guest-requests.index');;
    Route::post('guest-requests/{request}/acknowledge', [GuestRequestController::class, 'acknowledge']);
    Route::post('guest-requests/{request}/complete', [GuestRequestController::class, 'complete']);

    Route::get('billing/{booking}', [BillingController::class, 'viewBill']);
    Route::post('billing/{booking}/pay', [BillingController::class, 'acceptPayment']);

    Route::get('reports/occupancy', [ReportController::class, 'occupancyReport']);
    Route::get('reports/revenue', [ReportController::class, 'revenueReport']);
    Route::get('reports/bookings', [ReportController::class, 'bookingHistoryReport']);

    Route::get('/laundry-requests', [FrontDeskLaundryController::class, 'index'])->name('laundry.index');
    Route::get('/laundry-requests/{guestRequest}', [FrontDeskLaundryController::class, 'show'])->name('frontdesk.laundry.show');

    Route::get('/receipts', [ReceiptController::class, 'index']);
    Route::get('/receipts/{receipt}', [ReceiptController::class, 'show']);

});


// Laundry Staff
Route::middleware(['auth', 'role:laundry|frontdesk'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/laundry', [LaundryStaffController::class, 'index'])->name('laundry.dashboard');
    Route::post('/laundry/{order}/status', [LaundryStaffController::class, 'updateStatus'])->name('laundry.updateStatus');
    Route::get('/laundry/{order}', [LaundryStaffController::class, 'show'])->name('laundry.show');
    Route::post('/laundry/{order}/images', [LaundryStaffController::class, 'addImages'])->name('laundry.addImages');
    Route::get('laundry/{order}/print', [LaundryStaffController::class, 'print'])->name('laundry.print');

    Route::get('/laundry-items', [LaundryItemController::class, 'index'])->name('laundry-items.index');
    Route::post('/laundry-items', [LaundryItemController::class, 'store'])->name('laundry-items.store');
    Route::put('/laundry-items/{laundryItem}', [LaundryItemController::class, 'update'])->name('laundry-items.update');
    Route::delete('/laundry-items/{laundryItem}', [LaundryItemController::class, 'destroy'])->name('laundry-items.destroy');
});


//KITCHEN AND BAR ROUTES
Route::middleware(['auth', 'role:staff|manager|md|kitchen|bar'])->prefix('staff')->name('staff.')->group(function () {

    Route::get('/kitchen/orders', [KitchenOrderController::class, 'index'])->name('kitchen.orders.index');
    Route::patch('/kitchen/orders/{order}', [KitchenOrderController::class, 'updateStatus'])->name('kitchen.orders.updateStatus');

    Route::get('/bar/orders', [BarOrderController::class, 'index'])->name('bar.orders.index');
    Route::patch('/bar/orders/{order}', [BarOrderController::class, 'updateStatus'])->name('bar.orders.updateStatus');

    /* ==============================
     | KITCHEN DASHBOARD
     |==============================*/
    Route::get('/kitchen', [KitchenDashboardController::class, 'index'])->name('kitchen.dashboard');


    /* ==============================
     | BAR DASHBOARD
     |==============================*/
    Route::get('/bar', [BarDashboardController::class, 'index'])->name('bar.dashboard');


    /* ==============================
     | MENU MANAGEMENT (SHARED)
     |==============================*/
    Route::get('/menu', [MenuItemController::class, 'index'])->name('menu.index');

    Route::get('/menu/manage', [MenuItemController::class, 'index'])
        ->name('staff.menu.index');

    /* Categories */
    Route::post('/menu/categories', [MenuCategoryController::class, 'store']);
    Route::patch('/menu/categories/{category}', [MenuCategoryController::class, 'update']);
    Route::delete('/menu/categories/{category}', [MenuCategoryController::class, 'destroy']);

    /* Subcategories */
    Route::post('/menu/subcategories', [MenuSubcategoryController::class, 'store']);
    Route::patch('/menu/subcategories/{subcategory}', [MenuSubcategoryController::class, 'update']);
    Route::delete('/menu/subcategories/{subcategory}', [MenuSubcategoryController::class, 'destroy']);

    /* Items */
    Route::post('/menu/items', [MenuItemController::class, 'store']);
    Route::patch('/menu/items/{item}', [MenuItemController::class, 'update']);
    Route::delete('/menu/items/{item}', [MenuItemController::class, 'destroy']);
});

