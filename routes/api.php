<?php

use App\Http\Controllers\Api\NasFeedController;
use Illuminate\Support\Facades\Route;

// Public JSON endpoints for an external automation (a NAS running N8N) to
// pull today's birthdays, schedule and pending actions for automatic
// printing — no session, no Filament panel. An opaque per-teacher token
// (see User::api_token) is the only access control, same idiom as the ICS
// feed in routes/web.php. Registered twice, same controller methods: with
// the token in the path (simplest, works with any client) and without it,
// for clients that send `Authorization: Bearer <token>` instead — see
// NasFeedController::resolveUser().
Route::prefix('nas/{token}')->name('api.nas.')->group(function () {
    Route::get('/birthdays', [NasFeedController::class, 'birthdays'])->name('birthdays');
    Route::get('/schedule', [NasFeedController::class, 'schedule'])->name('schedule');
    Route::get('/reminders', [NasFeedController::class, 'reminders'])->name('reminders');
    Route::get('/homework', [NasFeedController::class, 'homework'])->name('homework');
});

Route::prefix('nas')->name('api.nas.bearer.')->group(function () {
    Route::get('/birthdays', [NasFeedController::class, 'birthdays'])->name('birthdays');
    Route::get('/schedule', [NasFeedController::class, 'schedule'])->name('schedule');
    Route::get('/reminders', [NasFeedController::class, 'reminders'])->name('reminders');
    Route::get('/homework', [NasFeedController::class, 'homework'])->name('homework');
});
