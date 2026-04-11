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
use App\Http\Controllers\Staff\CleaningDashboardController;
use App\Http\Controllers\Staff\CleaningActionController;
use App\Http\Controllers\Staff\StaffChargeController;
use App\Http\Controllers\Staff\EventCheckInController;
use App\Http\Controllers\Staff\MaintenanceDashboardController;
use App\Http\Controllers\Staff\StaffThreadController;
use App\Http\Controllers\FeedbackController;





/*
|--------------------------------------------------------------------------
| Staff Routes
|--------------------------------------------------------------------------
|
| Routes used by authenticated staff. Protected by auth + role middleware.
|
*/



Route::middleware(['auth', 'role:staff|manager|md|laundry|frontdesk|hr'])->prefix('staff')->name('staff.')->group(function () {

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

Route::middleware(['auth', 'role:staff|manager|md|frontdesk|laundry|hr|clean|kitchen|bar|inventory|accountant|Accountant|maintenance'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/threads', [StaffThreadController::class, 'index'])->name('threads.index');
    Route::get('/threads/create', [StaffThreadController::class, 'create'])->name('threads.create');
    Route::post('/threads', [StaffThreadController::class, 'store'])->name('threads.store');
    Route::get('/threads/{thread}', [StaffThreadController::class, 'show'])->name('threads.show');
    Route::post('/threads/{thread}/messages', [StaffThreadController::class, 'storeMessage'])->name('threads.messages.store');
    Route::get('/feedback', [FeedbackController::class, 'createStaff'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'storeStaff'])->name('feedback.store');
});

Route::middleware(['auth'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/maintenance', [MaintenanceDashboardController::class, 'index'])->name('maintenance.index');
    Route::patch('/maintenance/{ticket}/status', [MaintenanceDashboardController::class, 'updateStatus'])->name('maintenance.updateStatus');
});


Route::prefix('frontdesk')->middleware(['auth', 'role:frontdesk|md'])->name('frontdesk.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/rooms/{room}/billing', [RoomBillingController::class, 'show']);
    Route::post('/rooms/{room}/billing/pay', [RoomBillingController::class, 'pay']);

    Route::resource('bookings', BookingsController::class);
    Route::post('bookings/{booking}/checkin', [BookingsController::class, 'checkIn'])->name('bookings.checkIn');
    Route::post('bookings/{booking}/checkout', [BookingsController::class, 'checkOut'])->name('bookings.checkOut');
    Route::post('bookings/{booking}/extend', [BookingsController::class, 'extendStay'])->name('bookings.extendStay');

    Route::resource('rooms', RoomController::class)->only(['index', 'show', 'updateStatus']);
    Route::patch('rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.update-status');

    Route::get('guest-requests', [GuestRequestController::class, 'index'])->name('guest-requests.index');;
    Route::post('guest-requests/{request}/acknowledge', [GuestRequestController::class, 'acknowledge']);
    Route::post('guest-requests/{request}/complete', [GuestRequestController::class, 'complete']);

    Route::get('billing/{booking}', [BillingController::class, 'viewBill'])->name('billing.show');
    Route::post('billing/{booking}/pay', [BillingController::class, 'acceptPayment'])->name('billing.pay');
    Route::post('billing/{booking}/charge', [BillingController::class, 'addCharge'])->name('billing.charge');

    //Route::get('reports/occupancy', [ReportController::class, 'occupancyReport']);
    //Route::get('reports/revenue', [ReportController::class, 'revenueReport']);
    //Route::get('reports/bookings', [ReportController::class, 'bookingHistoryReport'])->name('reports.bookings');

    Route::get('/laundry-requests', [FrontDeskLaundryController::class, 'index'])->name('laundry.index');
    Route::get('/laundry-requests/{guestRequest}', [FrontDeskLaundryController::class, 'show'])->name('frontdesk.laundry.show');

    Route::get('/receipts', [ReceiptController::class, 'index']);
    Route::get('/receipts/{receipt}', [ReceiptController::class, 'show']);

});


// Laundry Staff
Route::middleware(['auth', 'role:laundry|frontdesk|md'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/laundry', [LaundryStaffController::class, 'index'])->name('laundry.dashboard');
    Route::post('/laundry', [LaundryStaffController::class, 'store'])->name('laundry.store');
    Route::post('/laundry/{order}/status', [LaundryStaffController::class, 'updateStatus'])->name('laundry.updateStatus');
    Route::get('/laundry/{order}', [LaundryStaffController::class, 'show'])->name('laundry.show');
    Route::post('/laundry/{order}/images', [LaundryStaffController::class, 'addImages'])->name('laundry.addImages');
    Route::get('laundry/{order}/print', [LaundryStaffController::class, 'print'])->name('laundry.print');

    Route::get('/laundry-items', [LaundryItemController::class, 'index'])->name('laundry-items.index');
    Route::post('/laundry-items', [LaundryItemController::class, 'store'])->name('laundry-items.store');
    Route::put('/laundry-items/{laundryItem}', [LaundryItemController::class, 'update'])->name('laundry-items.update');
    Route::delete('/laundry-items/{laundryItem}', [LaundryItemController::class, 'destroy'])->name('laundry-items.destroy');
});

//Cleaning Routes
Route::middleware(['auth', 'role:staff|clean|md'])->prefix('clean')->name('clean.')->group(function () {
    Route::get('/', [CleaningDashboardController::class, 'index'])->name('dashboard');
    //Route::patch('/clean/{cleaning}', [CleaningDashboardController::class, 'update'])->name('update');
    Route::patch('/{cleaning}', [CleaningDashboardController::class, 'update'])->name('update');
});


//KITCHEN AND BAR ROUTES
Route::middleware(['auth', 'role:frontdesk|manager|md|kitchen|bar'])->prefix('staff')->name('staff.')->group(function () {

    Route::get('/kitchen/orders', [KitchenOrderController::class, 'index'])->name('kitchen.orders.index');
    Route::post('/kitchen/orders', [KitchenOrderController::class, 'store'])->name('kitchen.orders.store');
    Route::patch('/kitchen/orders/{order}', [KitchenOrderController::class, 'updateStatus'])->name('kitchen.orders.updateStatus');
    Route::patch('/kitchen/orders/{order}/payment', [KitchenOrderController::class, 'updatePayment'])->name('kitchen.orders.updatePayment');
    Route::get('/kitchen/orders/history', [KitchenOrderController::class, 'history'])->name('kitchen.orders.history');

    Route::get('/bar/orders', [BarOrderController::class, 'index'])->name('bar.orders.index');
    Route::post('/bar/orders', [BarOrderController::class, 'store'])->name('bar.orders.store');
    Route::patch('/bar/orders/{order}', [BarOrderController::class, 'updateStatus'])->name('bar.orders.updateStatus');
    Route::patch('/bar/orders/{order}/payment', [BarOrderController::class, 'updatePayment'])->name('bar.orders.updatePayment');
    Route::get('/bar/orders/history', [BarOrderController::class, 'history'])->name('bar.orders.history');

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
    Route::get('/kitchen/menu', [MenuItemController::class, 'index'])->defaults('area', 'kitchen')->name('menu.kitchen');

    Route::get('/bar/menu', [MenuItemController::class, 'index'])->defaults('area', 'bar')->name('menu.bar');


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
    Route::delete('/menu/items/images/{image}', [MenuItemController::class, 'deleteImage']);



    /* ==============================
    | MENU VIEW / EDIT
    |==============================*/

    // View menu tree

    // Edit item
    Route::get('/menu/items/{item}/edit', [MenuItemController::class, 'edit'])
        ->name('menu.items.edit');
    Route::patch('/menu/items/{item}/toggle', [MenuItemController::class, 'toggle']);

    // Edit category
    Route::get('/menu/categories/{category}/edit', [MenuCategoryController::class, 'edit'])
        ->name('menu.categories.edit');
    Route::patch('/menu/categories/{category}/toggle', [MenuCategoryController::class, 'toggle']);

    // Edit subcategory
    Route::get('/menu/subcategories/{subcategory}/edit', [MenuSubcategoryController::class, 'edit'])
        ->name('menu.subcategories.edit');
    Route::patch('/menu/subcategories/{subcategory}/toggle', [MenuSubcategoryController::class, 'toggle']);

    // Drag & drop ordering
    Route::post('/menu/reorder', [MenuCategoryController::class, 'reorder']);
    Route::post('/menu/reorder-items', [MenuItemController::class, 'reorder']);

});

Route::post(
    '/staff/charges/{charge}/mark-paid',
    [StaffChargeController::class, 'markAsPaid']
)->middleware(['auth', 'role:kitchen|bar|staff|manager|md|laundry'])->name('staff.charges.markPaid');

// Event Check-In Routes
Route::middleware(['auth', 'role:staff|manager|md|frontdesk'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/events/check-in', [EventCheckInController::class, 'index'])->name('events.check-in.index');
    Route::get('/events/scan', [EventCheckInController::class, 'scan'])->name('events.check-in.scan');
    Route::post('/events/validate', [EventCheckInController::class, 'validateQrCode'])->name('events.check-in.validate');
    Route::post('/events/check-in', [EventCheckInController::class, 'checkIn'])->name('events.check-in.process');
    Route::get('/events/stats/today', [EventCheckInController::class, 'todayStats'])->name('events.check-in.stats');
});
