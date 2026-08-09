<?php

use App\Http\Controllers\Api\NasFeedController;
use Illuminate\Support\Facades\Route;

// Public JSON endpoints for an external automation (a NAS running N8N) to
// pull today's birthdays, schedule and pending actions for automatic
// printing — no session, no Filament panel. The token in the path is the
// only access control (see User::api_token), same idiom as the ICS feed in
// routes/web.php.
Route::prefix('nas/{token}')->name('api.nas.')->group(function () {
    Route::get('/birthdays', [NasFeedController::class, 'birthdays'])->name('birthdays');
    Route::get('/schedule', [NasFeedController::class, 'schedule'])->name('schedule');
    Route::get('/reminders', [NasFeedController::class, 'reminders'])->name('reminders');
    Route::get('/homework', [NasFeedController::class, 'homework'])->name('homework');
});
