

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
    SettingController,
    StaffThreadController,
    StaffThreadMessagesController,
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

        Route::prefix('staff')->name('staff.')->middleware(['auth','role:Manager|MD'])->group(function(){
            Route::resource('', StaffController::class)->parameters([
                '' => 'staff'
            ]);
            Route::get('{staff}/threads', [StaffThreadController::class,'index'])->name('threads.index');
            Route::get('{staff}/threads/create', [StaffThreadController::class,'create'])->name('threads.create');
            Route::get('threads/{thread}', [StaffThreadController::class,'show'])->name('threads.show');
            Route::post('threads/{thread}/messages', [StaffThreadController::class,'storeMessage'])->name('threads.messages.store');
            Route::post('{staff}/threads', [StaffThreadController::class,'createThread'])->name('threads.store');
            Route::post('{staff}/suspend', [StaffController::class, 'suspend'])->name('staff.suspend');
            Route::post('{staff}/reinstate', [StaffController::class, 'reinstate'])->name('staff.reinstate');
            Route::post('{staff}/notes', [StaffController::class, 'addNote']);

        });

        Route::resource('inventory', InventoryController::class);
        Route::post('inventory/{inventory}/use', [InventoryController::class,'useItem'])->name('inventory.useItem');
        Route::get('inventory/{inventory}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::post('inventory/{inventory}/use',[InventoryController::class, 'useItem'])->name('inventory.useItem');
        Route::post('inventory/logs/{log}/undo', [InventoryController::class, 'undoLog'])->name('inventory.logs.undo');





        Route::get('maintenance', [MaintenanceAdminController::class, 'index'])->name('maintenance.index');
        Route::get('maintenance/{ticket}', [MaintenanceAdminController::class, 'show'])->name('maintenance.show');
        Route::put('maintenance/{ticket}', [MaintenanceAdminController::class, 'update'])->name('maintenance.update');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/occupancy', [ReportController::class, 'occupancy'])->name('reports.occupancy');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
