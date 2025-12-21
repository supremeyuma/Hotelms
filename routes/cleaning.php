<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Staff\CleaningDashboardController;
use App\Http\Controllers\Staff\CleaningActionController;

//Cleaning Routes
Route::prefix('cleaning')->name('cleaining.')->group(function () {
    Route::get('/', [CleaningDashboardController::class, 'index'])->name('dashboard');
    //Route::patch('/cleaning/{cleaning}', [CleaningDashboardController::class, 'update'])->name('update');
    Route::patch('/{cleaning}', [CleaningDashboardController::class, 'update'])->name('update');
});
