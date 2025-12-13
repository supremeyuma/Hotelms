<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\BookingAdminController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\MaintenanceAdminController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\OrderController;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Admin and manager routes. Protected by auth and role:manager|md middleware.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {

    // Admin dashboard route (can map to reports/index or a dedicated controller)
    Route::get('/', [ReportController::class, 'index'])->name('dashboard');

    // Rooms & Room Types
    Route::resource('rooms', RoomController::class);
    Route::post('rooms/{room}/toggle-availability', [RoomController::class, 'toggleAvailability'])->name('rooms.toggle');

    Route::resource('room-types', RoomTypeController::class, ['as' => 'roomtypes']);

    // Bookings - list, edit, reassign, delete
    Route::get('bookings', [BookingAdminController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}/edit', [BookingAdminController::class, 'edit'])->name('bookings.edit');
    Route::patch('bookings/{booking}', [BookingAdminController::class, 'update'])->name('bookings.update');
    Route::delete('bookings/{booking}', [BookingAdminController::class, 'destroy'])->name('bookings.destroy');
    Route::post('bookings/{booking}/reassign', [BookingAdminController::class, 'reassignRoom'])->name('bookings.reassign');

    // Orders management
    Route::get('orders', [OrderController::class, 'kitchenQueue'])->name('orders.index'); // general view, controllers will handle specifics

    // Staff management
    Route::resource('staff', StaffController::class);

    // Inventory
    Route::resource('inventory', InventoryController::class);
    Route::post('inventory/{inventory}/use', [InventoryController::class, 'useItem'])->name('inventory.use');

    // Maintenance admin
    Route::resource('maintenance', MaintenanceAdminController::class);
    Route::post('maintenance/{maintenance}/assign', [MaintenanceAdminController::class, 'assign'])->name('maintenance.assign');
    Route::post('maintenance/{maintenance}/status', [MaintenanceAdminController::class, 'updateStatus'])->name('maintenance.status');
    Route::post('maintenance/{maintenance}/close', [MaintenanceAdminController::class, 'close'])->name('maintenance.close');

    // Reports & audit logs
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/occupancy', [ReportController::class, 'occupancyDetail'])->name('reports.occupancy');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');
});
