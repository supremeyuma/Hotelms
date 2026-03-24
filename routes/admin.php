

<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
    DashboardController,
    RoomController,
    RoomTypeController,
    BookingAdminController,
    OrderAdminController,
    InventoryController,
    MaintenanceAdminController,
    ReportController,
    AuditLogController,
    SettingController,
    InventoryLocationController,
    CleaningInventoryTemplateController,
    MenuInventoryRecipeController,
    OutstandingBalancesController,
    AccountingPeriodController,
    EventController,
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
use App\Http\Controllers\Reports\ProfitAndLossController;

Route::middleware(['auth', 'role:manager|md'])->prefix('admin')->as('admin.')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('rooms', RoomController::class);
        Route::resource('room-types', RoomTypeController::class);

        Route::get('bookings', [BookingAdminController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}/edit', [BookingAdminController::class, 'edit'])->name('bookings.edit');
        Route::put('bookings/{booking}', [BookingAdminController::class, 'update'])->name('bookings.update');

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

            Route::get('profit-loss', [ProfitAndLossController::class, 'index'])->name('profit-loss');
            Route::get('balance-sheet', [\App\Http\Controllers\Reports\BalanceSheetController::class, 'index'])->name('balance-sheet');
            Route::get('daily-revenue', [\App\Http\Controllers\Reports\DailyRevenueController::class, 'index'])->name('daily-revenue');

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

        // Outstanding Balances
        Route::get('outstanding-balances', [OutstandingBalancesController::class, 'index'])->name('outstanding-balances.index');
        Route::get('outstanding-balances/export', [OutstandingBalancesController::class, 'export'])->name('outstanding-balances.export');

        // Accounting Periods
        Route::get('accounting-periods', [AccountingPeriodController::class, 'index'])->name('accounting-periods.index');
        Route::post('accounting-periods', [AccountingPeriodController::class, 'store'])->name('accounting-periods.store');
        Route::post('accounting-periods/{period}/close', [AccountingPeriodController::class, 'close'])->name('accounting-periods.close');
        Route::post('accounting-periods/{period}/reopen', [AccountingPeriodController::class, 'reopen'])->name('accounting-periods.reopen');
        Route::get('accounting-periods/{period}', [AccountingPeriodController::class, 'show'])->name('accounting-periods.show');
        Route::post('accounting-periods/initialize-tax-accounts', [AccountingPeriodController::class, 'initializeTaxAccounts'])->name('accounting-periods.initialize-tax-accounts');
        Route::get('accounting-periods/check-status', [AccountingPeriodController::class, 'checkPeriodStatus'])->name('accounting-periods.check-status');
        Route::post('accounting-periods/auto-close-expired', [AccountingPeriodController::class, 'autoCloseExpired'])->name('accounting-periods.auto-close-expired');
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
    Route::get('/admin/events/create', [EventController::class,'create'])->name('admin.events.create');
    Route::get('/admin/events/{event}', [EventController::class,'show'])->name('admin.events.show');
    Route::get('/admin/events/{event}/edit', [EventController::class,'edit'])->name('admin.events.edit');
    Route::put('/admin/events/{event}', [EventController::class,'update'])->name('admin.events.update');
    Route::delete('/admin/events/{event}', [EventController::class,'destroy'])->name('admin.events.destroy');
    
    Route::post('/admin/events/{event}/ticket-types', [EventController::class,'storeTicketTypes'])->name('admin.events.ticket-types.store');
    Route::post('/admin/events/{event}/upload-media', [EventController::class,'uploadPromotionalMedia'])->name('admin.events.upload-media');
    Route::delete('/admin/events/{event}/media/{media}', [EventController::class,'deletePromotionalMedia'])->name('admin.events.delete-media');
    Route::post('/admin/events/{event}/media/reorder', [EventController::class,'reorderPromotionalMedia'])->name('admin.events.reorder-media');
    Route::post('/admin/events/{event}/feature', [EventController::class,'featureEvent'])->name('admin.events.feature');
    Route::post('/admin/events/{event}/unfeature', [EventController::class,'unfeatureEvent'])->name('admin.events.unfeature');
    });
