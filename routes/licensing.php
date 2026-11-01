<?php

use App\Http\Controllers\LicenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Licence activation
|--------------------------------------------------------------------------
|
| Called by the desktop app, not by a browser: the customer pastes the key they
| bought, the app sends it with its machine id, and receives a signed licence
| file it verifies offline. Throttled because keys are short enough to guess.
|
*/

Route::post('/licence/activate', [LicenceController::class, 'activate'])
    ->middleware('throttle:10,1')
    ->name('licence.activate');
