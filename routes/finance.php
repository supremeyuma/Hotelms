<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OutstandingBalancesController;
use App\Http\Controllers\Admin\AccountingPeriodController;
use App\Http\Controllers\Admin\ReportDashboardController;
use App\Http\Controllers\Admin\Reports\RevenueReportController;
use App\Http\Controllers\Reports\ProfitAndLossController;
use App\Http\Controllers\Reports\BalanceSheetController;
use App\Http\Controllers\Reports\DailyRevenueController;
use App\Http\Controllers\Admin\Reports\ChartController;

Route::middleware(['auth', 'role:accountant|Accountant|md'])->prefix('finance')->as('finance.')->group(function () {
    Route::get('/dashboard', [ReportDashboardController::class, 'finance'])->name('dashboard');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/revenue', [RevenueReportController::class, 'index'])->name('revenue');
        Route::get('/revenue/export/{format}', [RevenueReportController::class, 'export'])->name('revenue.export');

        Route::get('/profit-loss', [ProfitAndLossController::class, 'index'])->name('profit-loss');
        Route::get('/profit-loss/export/csv', [ProfitAndLossController::class, 'export'])->name('profit-loss.export');
        Route::get('/balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet');
        Route::get('/balance-sheet/export/csv', [BalanceSheetController::class, 'export'])->name('balance-sheet.export');
        Route::get('/daily-revenue', [DailyRevenueController::class, 'index'])->name('daily-revenue');
        Route::get('/daily-revenue/export/csv', [DailyRevenueController::class, 'export'])->name('daily-revenue.export');

        Route::prefix('charts')->name('charts.')->group(function () {
            Route::get('/revenue', [ChartController::class, 'revenue'])->name('revenue');
        });
    });

    Route::prefix('outstanding-balances')->name('outstanding-balances.')->group(function () {
        Route::get('/', [OutstandingBalancesController::class, 'index'])->name('index');
        Route::get('/export', [OutstandingBalancesController::class, 'export'])->name('export');
    });

    Route::prefix('accounting-periods')->name('accounting-periods.')->group(function () {
        Route::get('/', [AccountingPeriodController::class, 'index'])->name('index');
        Route::post('/', [AccountingPeriodController::class, 'store'])->name('store');
        Route::post('/initialize-tax-accounts', [AccountingPeriodController::class, 'initializeTaxAccounts'])->name('initialize-tax-accounts');
        Route::get('/check-status', [AccountingPeriodController::class, 'checkPeriodStatus'])->name('check-status');
        Route::post('/auto-close-expired', [AccountingPeriodController::class, 'autoCloseExpired'])->name('auto-close-expired');
        Route::post('/{period}/close', [AccountingPeriodController::class, 'close'])->whereNumber('period')->name('close');
        Route::post('/{period}/reopen', [AccountingPeriodController::class, 'reopen'])->whereNumber('period')->name('reopen');
        Route::get('/{period}', [AccountingPeriodController::class, 'show'])->whereNumber('period')->name('show');
    });

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
});
