<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClubPos\DashboardController;
use App\Http\Controllers\ClubPos\DocketController;
use App\Http\Controllers\ClubPos\ShiftController;
use App\Http\Controllers\ClubPos\StockController;
use App\Http\Controllers\ClubPos\ReportController;

Route::middleware('auth')->post('/pos/staff/switch', function (Illuminate\Http\Request $request) {
    $validated = $request->validate(['pin' => 'required|string|min:4|max:10']);

    $user = \App\Models\User::whereHas('roles', function ($q) {
        $q->whereIn('name', ['club_staff', 'club_supervisor']);
    })->whereHas('staffProfile', function ($q) use ($validated) {
        $q->where('action_code', $validated['pin']);
    })->first();

    if (!$user) {
        return response()->json(['error' => 'Invalid PIN.'], 422);
    }

    \Illuminate\Support\Facades\Auth::login($user);
    session()->regenerate();
    session()->put('pos_pin_verified', true);

    return response()->json(['success' => true]);
})->name('pos.staff.switch');

Route::middleware(['auth', 'permission:club.pos.access'])->prefix('club')->name('club.')->group(function () {

    /* ==============================
     | POS DASHBOARD
     |==============================*/
    Route::get('/pos', [DashboardController::class, 'index'])->name('pos.dashboard');
    Route::get('/pos/data', [DashboardController::class, 'posData'])->name('pos.data');

    /* ==============================
     | DOCKETS
     |==============================*/
    Route::post('/pos/dockets', [DocketController::class, 'store'])->name('pos.dockets.store');
    Route::get('/pos/dockets/{docket}', [DocketController::class, 'show'])->name('pos.dockets.show');
    Route::post('/pos/dockets/{docket}/items', [DocketController::class, 'addItem'])->name('pos.dockets.items.store');
    Route::delete('/pos/dockets/{docket}/items/{item}', [DocketController::class, 'removeItem'])->name('pos.dockets.items.destroy');
    Route::post('/pos/dockets/{docket}/pay', [DocketController::class, 'pay'])->name('pos.dockets.pay');
    Route::post('/pos/dockets/{docket}/void', [DocketController::class, 'void'])->name('pos.dockets.void');

    /* ==============================
     | SHIFTS
     |==============================*/
    Route::post('/pos/shifts', [ShiftController::class, 'open'])->name('pos.shifts.open');
    Route::post('/pos/shifts/{session}/close', [ShiftController::class, 'close'])->name('pos.shifts.close');
    Route::post('/pos/shifts/{session}/reconcile', [ShiftController::class, 'reconcile'])->name('pos.shifts.reconcile');
    Route::get('/pos/shifts/current', [ShiftController::class, 'current'])->name('pos.shifts.current');
    Route::get('/pos/shifts/{session}', [ShiftController::class, 'show'])->name('pos.shifts.show');

    /* ==============================
     | STOCK
     |==============================*/
    Route::get('/pos/stock', [StockController::class, 'index'])->name('pos.stock.index');
    Route::post('/pos/stock/{menuItem}/add', [StockController::class, 'add'])->name('pos.stock.add');
    Route::post('/pos/stock/{menuItem}/stocktake', [StockController::class, 'stocktake'])->name('pos.stock.stocktake');

    /* ==============================
     | REPORTS
     |==============================*/
    Route::get('/pos/reports/sales', [ReportController::class, 'sales'])->name('pos.reports.sales');
    Route::get('/pos/reports/trends', [ReportController::class, 'trends'])->name('pos.reports.trends');
    Route::get('/pos/reports/stock', [ReportController::class, 'stock'])->name('pos.reports.stock');
});
