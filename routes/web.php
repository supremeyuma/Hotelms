<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Staff\StaffDashboardController;
use App\Http\Controllers\Staff\StaffActionController;
use App\Http\Controllers\Staff\StaffOrderController;
use App\Http\Controllers\Staff\StaffProfileController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\BookingAdminController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MaintenanceAdminController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\RoomServiceController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (Guest-facing pages)
|--------------------------------------------------------------------------
*/

// Homepage
Route::get('/', [PublicController::class, 'home'])->name('home');

// Rooms listing
Route::get('/rooms', [PublicController::class, 'showRoomTypes'])->name('rooms.index');

// Single room detail
Route::get('/rooms/{room}', [PublicController::class, 'roomDetail'])
    ->name('rooms.show');

// Booking form
Route::get('/booking/{room}', [PublicController::class, 'bookingForm'])
    ->name('booking.form');

// Booking confirmation
Route::get('/booking/{booking}/confirm', [PublicController::class, 'bookingConfirmation'])
    ->name('booking.confirm');

// Gallery
Route::get('/gallery', [PublicController::class, 'gallery'])->name('gallery');

// Contact page
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');


/*
|--------------------------------------------------------------------------
| ROOM SERVICE ROUTES (QR code entry)
|--------------------------------------------------------------------------
|
| /room/{room}/service
|
*/

Route::prefix('/room/{room}/service')->group(function () {

    Route::get('/', [RoomServiceController::class, 'index'])
        ->name('room.service');

    // Kitchen Orders
    Route::post('/kitchen', [RoomServiceController::class, 'kitchen'])
        ->name('room.service.kitchen');

    // Laundry
    Route::post('/laundry', [RoomServiceController::class, 'laundry'])
        ->name('room.service.laundry');

    // Housekeeping
    Route::post('/housekeeping', [RoomServiceController::class, 'housekeeping'])
        ->name('room.service.housekeeping');

    // Maintenance Request
    Route::post('/maintenance', [RoomServiceController::class, 'maintenance'])
        ->name('room.service.maintenance');
});


/*
|--------------------------------------------------------------------------
| STAFF PORTAL ROUTES (Authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:staff|manager|md'])
    ->prefix('staff')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [StaffDashboardController::class, 'index'])
            ->name('staff.dashboard');

        // Quick Action Code Page
        Route::get('/quick-action', [StaffActionController::class, 'index'])
            ->name('staff.quick-action');
        Route::post('/quick-action/verify', [StaffActionController::class, 'verify'])
            ->name('staff.quick-action.verify');

        // Staff Orders Queue
        Route::get('/orders', [StaffOrderController::class, 'index'])
            ->name('staff.orders.queue');

        // Profile
        Route::get('/profile', [StaffProfileController::class, 'index'])
            ->name('staff.profile');
        Route::patch('/profile', [StaffProfileController::class, 'update'])
            ->name('staff.profile.update');
    });


/*
|--------------------------------------------------------------------------
| ADMIN / MANAGEMENT ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:manager|md'])
    ->prefix('admin')
    ->group(function () {

        // Rooms CRUD
        Route::resource('rooms', RoomController::class);

        // Room Types CRUD
        Route::resource('room-types', RoomTypeController::class);

        // Bookings
        Route::get('bookings', [BookingAdminController::class, 'index'])
            ->name('admin.bookings.index');

        // Staff management
        Route::resource('staff', StaffController::class);

        // Inventory
        Route::resource('inventory', InventoryController::class);

        // Maintenance Tickets
        Route::resource('maintenance', MaintenanceAdminController::class);

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])
            ->name('admin.reports');

        // Audit Logs
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('admin.audit-logs');

        // Settings
        Route::get('/settings', [SettingController::class, 'index'])
            ->name('admin.settings');
        Route::patch('/settings', [SettingController::class, 'update'])
            ->name('admin.settings.update');
    });


/*
|--------------------------------------------------------------------------
| ORDER WORKFLOW ROUTES
|--------------------------------------------------------------------------
*/

// Place order
Route::post('/order', [OrderController::class, 'store'])
    ->name('order.store');

// Update order status
Route::patch('/order/{order}/status', [OrderController::class, 'updateStatus'])
    ->name('order.status.update');

// Order queues
Route::get('/orders/kitchen', [OrderController::class, 'kitchenQueue'])
    ->name('orders.kitchen');
Route::get('/orders/laundry', [OrderController::class, 'laundryQueue'])
    ->name('orders.laundry');
Route::get('/orders/housekeeping', [OrderController::class, 'housekeepingQueue'])
    ->name('orders.housekeeping');
Route::get('/orders/maintenance', [OrderController::class, 'maintenanceQueue'])
    ->name('orders.maintenance');

require __DIR__.'/auth.php';
