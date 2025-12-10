<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;

/*
|--------------------------------------------------------------------------
| Image Upload & Management Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:manager|md|staff'])->group(function () {
    // Store image for a resource type (rooms, room-types, facilities, areas, properties)
    Route::post('/images/{type}/{id}', [ImageController::class, 'store'])->name('images.store');

    // Delete image
    Route::delete('/images/{image}', [ImageController::class, 'destroy'])->name('images.destroy');

    // Set primary
    Route::patch('/images/{image}/primary', [ImageController::class, 'setPrimary'])->name('images.primary');
});
