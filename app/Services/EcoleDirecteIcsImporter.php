<?php

namespace App\Services;

use App\Models\EcoleDirecteEvent;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Sabre\VObject\Reader;

/**
 * Fetches and parses the teacher's own Ecole-Directe ICS subscription URL
 * (a normal read-only ICS feed they already have — no API/credentials) and
 * caches its occurrences locally so LogbookEntry can link a séance to a
 * real timetable slot without CarnetProf ever inventing its own schedule.
 */
class EcoleDirecteIcsImporter
{
    public function importForUser(User $user): int
    {
        if (blank($user->ecole_directe_ics_url)) {
            return 0;
        }

        $body = Http::timeout(15)->get($user->ecole_directe_ics_url)->throw()->body();
        $calendar = Reader::read($body);

        $count = 0;
        $seenUids = [];

        foreach ($calendar->VEVENT ?? [] as $vevent) {
            $uid = (string) $vevent->UID;

            if (blank($uid) || ! isset($vevent->DTSTART)) {
                continue;
            }

            $seenUids[] = $uid;

            EcoleDirecteEvent::query()
                ->withoutGlobalScope('teacher')
                ->updateOrCreate(
                    ['user_id' => $user->id, 'uid' => $uid],
                    [
                        'title' => (string) $vevent->SUMMARY,
                        'starts_at' => $vevent->DTSTART->getDateTime(),
                        'ends_at' => isset($vevent->DTEND) ? $vevent->DTEND->getDateTime() : null,
                    ]
                );

            $count++;
        }

        // Occurrences no longer present in the feed (moved or removed on
        // Ecole-Directe's side) are dropped — logbook_entries.ecole_directe_event_id
        // is nullOnDelete(), so any séance linked to one of these just loses
        // its attachment; the séance itself (content, homework...) is
        // untouched. Skipped entirely when the feed came back empty, so a
        // transient fetch hiccup can never wipe every cached occurrence.
        if ($seenUids !== []) {
            EcoleDirecteEvent::query()
                ->withoutGlobalScope('teacher')
                ->where('user_id', $user->id)
                ->whereNotIn('uid', $seenUids)
                ->delete();
        }

        $user->forceFill(['ecole_directe_synced_at' => now()])->save();

        return $count;
    }
}
