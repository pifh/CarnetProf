<?php

namespace App\Services;

use App\Models\EcoleDirecteEvent;
use App\Models\User;
use DateTimeZone;
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
        $appTimezone = new DateTimeZone(config('app.timezone'));

        foreach ($calendar->VEVENT ?? [] as $vevent) {
            $uid = (string) $vevent->UID;

            if (blank($uid) || ! isset($vevent->DTSTART)) {
                continue;
            }

            $seenUids[] = $uid;

            // `starts_at`/`ends_at` are naive datetime columns read back
            // elsewhere as app-timezone (Europe/Paris) wall-clock time, so a
            // feed event carrying its own zone (UTC "Z", a TZID, or none at
            // all) must be normalized to Europe/Paris here — otherwise a
            // 09:30 Paris event sent as "07:30Z" would be saved as literal
            // "07:30" and displayed two hours early.
            $starts = $vevent->DTSTART->getDateTime($appTimezone)?->setTimezone($appTimezone);
            $ends = isset($vevent->DTEND) ? $vevent->DTEND->getDateTime($appTimezone)?->setTimezone($appTimezone) : null;

            EcoleDirecteEvent::query()
                ->withoutGlobalScope('teacher')
                ->updateOrCreate(
                    ['user_id' => $user->id, 'uid' => $uid],
                    [
                        'title' => (string) $vevent->SUMMARY,
                        'room' => $this->propertyValue($vevent, ['LOCATION']),
                        'group_name' => $this->extractGroup($vevent),
                        'starts_at' => $starts,
                        'ends_at' => $ends,
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

    /**
     * @param  array<int, string>  $propertyNames
     */
    private function propertyValue(object $vevent, array $propertyNames): ?string
    {
        foreach ($propertyNames as $propertyName) {
            if (! isset($vevent->{$propertyName})) {
                continue;
            }

            $value = trim((string) $vevent->{$propertyName});

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function extractGroup(object $vevent): ?string
    {
        $explicitGroup = $this->propertyValue($vevent, [
            'X-ECOLEDIRECTE-GROUP',
            'X-ECOLE-DIRECTE-GROUP',
            'X-GROUP',
            'GROUP',
        ]);

        if ($explicitGroup) {
            return $explicitGroup;
        }

        $description = $this->propertyValue($vevent, ['DESCRIPTION']);

        if (! $description) {
            return $this->propertyValue($vevent, ['CATEGORIES']);
        }

        if (preg_match('/(?:groupe|classe)\s*:\s*([^\r\n]+)/iu', $description, $matches) === 1) {
            return trim($matches[1]);
        }

        // Some Ecole Directe calendars expose only the group name in the
        // description, without a label. Preserve that concise value, but do
        // not import a longer free-text description into the planning API.
        return mb_strlen($description) <= 120 && ! str_contains($description, "\n")
            ? $description
            : null;
    }
}
