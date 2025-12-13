

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
    DashboardController,
    RoomController,
    RoomTypeController,
    BookingAdminController,
    OrderAdminController,
    StaffController,
    InventoryController,
    MaintenanceAdminController,
    ReportController,
    AuditLogController,
    SettingController
};

Route::middleware(['auth', 'role:Manager|MD'])
    ->prefix('admin')
    ->as('admin.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('rooms', RoomController::class);
        Route::resource('room-types', RoomTypeController::class);

        Route::get('bookings', [BookingAdminController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}/edit', [BookingAdminController::class, 'edit'])->name('bookings.edit');
        Route::put('bookings/{booking}', [BookingAdminController::class, 'update'])->name('bookings.update');

        Route::get('orders', [OrderAdminController::class, 'index'])->name('orders.index');
        Route::put('orders/{order}/status', [OrderAdminController::class, 'updateStatus'])->name('orders.updateStatus');

        Route::resource('staff', StaffController::class);
        Route::resource('inventory', InventoryController::class);

        Route::get('maintenance', [MaintenanceAdminController::class, 'index'])->name('maintenance.index');
        Route::get('maintenance/{ticket}', [MaintenanceAdminController::class, 'show'])->name('maintenance.show');
        Route::put('maintenance/{ticket}', [MaintenanceAdminController::class, 'update'])->name('maintenance.update');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/occupancy', [ReportController::class, 'occupancy'])->name('reports.occupancy');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
