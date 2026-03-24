<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StaffThreadController;

Route::middleware(['auth', 'role:hr|md'])->prefix('hr')->as('hr.')->group(function () {
    Route::redirect('/', '/hr/staff')->name('dashboard');

    Route::prefix('staff')->name('staff.')->group(function () {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::get('/create', [StaffController::class, 'create'])->name('create');
        Route::post('/', [StaffController::class, 'store'])->name('store');
        Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
        Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
        Route::delete('/{staff}', [StaffController::class, 'destroy'])->name('destroy');
        Route::post('/{staff}/suspend', [StaffController::class, 'suspend'])->name('suspend');
        Route::post('/{staff}/reinstate', [StaffController::class, 'reinstate'])->name('reinstate');
        Route::post('/{staff}/notes', [StaffController::class, 'addNote'])->name('notes.store');

        Route::get('/{staff}/threads', [StaffThreadController::class, 'index'])->name('threads.index');
        Route::get('/{staff}/threads/create', [StaffThreadController::class, 'create'])->name('threads.create');
        Route::post('/{staff}/threads', [StaffThreadController::class, 'createThread'])->name('threads.store');
        Route::get('/threads/{thread}', [StaffThreadController::class, 'show'])->name('threads.show');
        Route::post('/threads/{thread}/messages', [StaffThreadController::class, 'storeMessage'])->name('threads.messages.store');
    });
});
