<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarFeedGenerator;
use Illuminate\Http\Response;

/**
 * Publicly reachable (no Filament panel session) so external calendar apps
 * (Apple Calendar, Google Calendar) can poll it directly by URL — the
 * secret token in the path is the only access control.
 */
class CalendarFeedController extends Controller
{
    public function __invoke(string $token, CalendarFeedGenerator $generator): Response
    {
        $user = User::withoutGlobalScopes()->where('calendar_token', $token)->firstOrFail();

        $ics = $generator->generateForUser($user)->serialize();

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="carnetprof.ics"',
        ]);
    }
}
