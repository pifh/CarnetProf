<?php

use App\Http\Controllers\CalendarFeedController;
use Illuminate\Support\Facades\Route;

// Public ICS subscription feed — deliberately outside the Filament panel's
// authenticated middleware stack, since external calendar apps (Apple
// Calendar, Google Calendar) poll this URL directly with no session. The
// token in the path is the only access control (see User::calendar_token).
Route::get('/calendar/{token}.ics', CalendarFeedController::class)->name('calendar.feed');
