

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
    InventoryLocationController,
    CleaningInventoryTemplateController,
    MenuInventoryRecipeController,
};
use App\Http\Controllers\Admin\ReportDashboardController;
use App\Http\Controllers\Admin\Reports\StaffReportController;
use App\Http\Controllers\Admin\Reports\RevenueReportController;
use App\Http\Controllers\Admin\Reports\MaintenanceReportController;
use App\Http\Controllers\Admin\Reports\InventoryReportController;
use App\Http\Controllers\Admin\Reports\OccupancyReportController;
use App\Http\Controllers\Admin\Reports\ChartController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\EventController;

Route::middleware(['auth', 'role:manager|md'])->prefix('admin')->as('admin.')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('rooms', RoomController::class);
        Route::resource('room-types', RoomTypeController::class);

        Route::get('bookings', [BookingAdminController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}/edit', [BookingAdminController::class, 'edit'])->name('bookings.edit');
        Route::put('bookings/{booking}', [BookingAdminController::class, 'update'])->name('bookings.update');

        Route::prefix('staff')->name('staff.')->middleware(['auth','role:manager|md'])->group(function(){
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

        Route::get('inventory',[InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('inventory',[InventoryController::class, 'store'])->name('inventory.store');
        Route::get('inventory/{inventory}/edit',[InventoryController::class, 'edit'])->name('inventory.edit');
        Route::put('inventory/{inventory}',[InventoryController::class, 'update'])->name('inventory.update');
        Route::post('inventory/{inventory}/use', [InventoryController::class,'useItem'])->name('inventory.useItem');
        Route::get('inventory/{inventory}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::post('inventory/{inventory}/add-stock',[InventoryController::class, 'addStock'])->name('inventory.addStock');
        Route::resource('inventory-locations',InventoryLocationController::class)->except(['show']);

        Route::get('cleaning-templates',[CleaningInventoryTemplateController::class, 'index'])->name('cleaning-templates.index');
        Route::post('cleaning-templates',[CleaningInventoryTemplateController::class, 'store'])->name('cleaning-templates.store');
        Route::delete('cleaning-templates/{template}',[CleaningInventoryTemplateController::class, 'destroy'])->name('cleaning-templates.destroy');

        Route::post('cleaning-templates/{template}',[CleaningInventoryTemplateController::class, 'update'])->name('cleaning-templates.update');

        Route::post('cleaning-templates/clone',[CleaningInventoryTemplateController::class, 'clone'])->name('cleaning-templates.clone');


        Route::get('menu-recipes',[MenuInventoryRecipeController::class, 'index'])->name('menu-recipes.index');

        Route::post('menu-recipes',[MenuInventoryRecipeController::class, 'store'])->name('menu-recipes.store');

        Route::delete('menu-recipes/{recipe}',[MenuInventoryRecipeController::class, 'destroy'])->name('menu-recipes.destroy');


        Route::get('maintenance', [MaintenanceAdminController::class, 'index'])->name('maintenance.index');
        Route::get('maintenance/{ticket}', [MaintenanceAdminController::class, 'show'])->name('maintenance.show');
        Route::put('maintenance/{ticket}', [MaintenanceAdminController::class, 'update'])->name('maintenance.update');

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportDashboardController::class, 'index'])->name('dashboard');

            Route::get('/staff', [StaffReportController::class, 'index'])->name('staff');
            Route::get('/staff/export/{format}', [StaffReportController::class, 'export'])->name('staff.export');

            Route::get('/revenue', [RevenueReportController::class, 'index'])->name('revenue');
            Route::get('/revenue/export/{format}', [RevenueReportController::class, 'export'])->name('revenue.export');

            Route::get('/occupancy', [OccupancyReportController::class, 'index'])->name('occupancy');
            Route::get('/occupancy/export/{format}', [OccupancyReportController::class, 'export'])->name('occupancy.export');

            Route::get('/inventory', [InventoryReportController::class, 'index'])->name('inventory');
            Route::get('/inventory/export/{format}', [InventoryReportController::class, 'export'])->name('inventory.export');


                Route::prefix('charts')->group(function () {
                    Route::get('/revenue', [ChartController::class, 'revenue']);
                    Route::get('/occupancy', [ChartController::class, 'occupancy']);
                    Route::get('/inventory', [ChartController::class, 'inventory']);
                });

            //Route::get('/maintenance', [MaintenanceReportController::class, 'index'])->name('maintenance');
            //Route::get('/maintenance/export/{format}', [MaintenanceReportController::class, 'export'])->name('maintenance.export');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });


    // TEXTS AND GALLERY LINKS

Route::middleware(['auth','role:manager|md'])->prefix('admin/website')->group(function () {

        Route::get('/content', [ContentController::class, 'index'])
            ->name('admin.website.content');

        Route::post('/content', [ContentController::class, 'store']);

        Route::put('/content/{key}', [ContentController::class, 'update'])
            ->where('key', '.*'); // 👈 VERY IMPORTANT (allows dots)

        Route::post('/content/image', [ContentController::class, 'uploadImage']);

        Route::get('/gallery', [GalleryController::class, 'index'])->name('admin.website.gallery');
        Route::post('/gallery', [GalleryController::class, 'store']);
        Route::delete('/gallery/{gallery}', [GalleryController::class, 'destroy']);
        Route::put('/gallery/{gallery}', [GalleryController::class, 'update']);
        Route::post('/gallery/reorder', [GalleryController::class, 'reorder']);
        Route::patch('/gallery/{gallery}/toggle', [GalleryController::class, 'toggle']);
    });


Route::middleware(['auth','role:manager|md'])->group(function () {
    Route::get('/admin/events', [EventController::class,'index'])->name('admin.events.index');
    Route::post('/admin/events', [EventController::class,'store']);
});
