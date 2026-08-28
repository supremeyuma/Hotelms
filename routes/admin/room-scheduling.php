<?php

use App\Http\Controllers\Admin\RoomAvailabilityScheduleController;
use App\Http\Controllers\Admin\RoomSchedulingController;
use App\Http\Controllers\Admin\RoomTypePriceScheduleController;

Route::prefix('room-scheduling')->name('room-scheduling.')->group(function () {
    Route::get('/', [RoomSchedulingController::class, 'index'])->name('dashboard');

    Route::get('room-type-prices', [RoomTypePriceScheduleController::class, 'index'])->name('room-type-prices.index');
    Route::post('room-type-prices', [RoomTypePriceScheduleController::class, 'store'])->name('room-type-prices.store');
    Route::put('room-type-prices/{schedule}', [RoomTypePriceScheduleController::class, 'update'])->name('room-type-prices.update');
    Route::delete('room-type-prices/{schedule}', [RoomTypePriceScheduleController::class, 'destroy'])->name('room-type-prices.destroy');

    Route::get('room-availability', [RoomAvailabilityScheduleController::class, 'index'])->name('room-availability.index');
    Route::post('room-availability', [RoomAvailabilityScheduleController::class, 'store'])->name('room-availability.store');
    Route::put('room-availability/{schedule}', [RoomAvailabilityScheduleController::class, 'update'])->name('room-availability.update');
    Route::delete('room-availability/{schedule}', [RoomAvailabilityScheduleController::class, 'destroy'])->name('room-availability.destroy');

    Route::post('bulk-update', [RoomSchedulingController::class, 'bulkUpdate'])->name('bulk-update');
});