<?php

namespace App\Services;

use App\DataTransferObjects\CalendarItem;
use App\Filament\Pages\CalendrierReglages;
use App\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Filament\Resources\LogbookEntries\LogbookEntryResource;
use App\Filament\Resources\StudentEvents\StudentEventResource;
use App\Models\CalendarEvent;
use App\Models\EcoleDirecteEvent;
use App\Models\LogbookEntry;
use App\Models\PersonalBirthday;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gathers every calendar-relevant record for a teacher into a flat list of
 * CalendarItem — the single data source shared by the ICS feed
 * (CalendarFeedGenerator) and the in-app day/week/month views (Calendrier
 * page). No date bounding: per-teacher volumes are small, and bounding risks
 * dropping a multi-day item (e.g. vacances) that starts before a visible
 * window but extends into it.
 */
class CalendarItemCollector
{
    /**
     * How many upcoming years of yearly-recurring birthdays to materialize
     * as concrete items (chosen over an RRULE-style abstraction for
     * simplicity — regenerated fresh on every call, so there's no staleness
     * concern from not tracking recurrence rules).
     */
    private const BIRTHDAY_YEARS_AHEAD = 3;

    /**
     * @return Collection<int, CalendarItem>
     */
    public function forUser(User $user): Collection
    {
        return collect()
            ->merge($this->logbookEntries($user))
            ->merge($this->ecoleDirecteEvents($user))
            ->merge($this->studentEvents($user))
            ->merge($this->calendarEvents($user, [CalendarEvent::TYPE_REUNION, CalendarEvent::TYPE_RDV], 'reunions_rdv'))
            ->merge($this->calendarEvents($user, [CalendarEvent::TYPE_ETABLISSEMENT], 'etablissement'))
            ->merge($this->calendarEvents($user, [CalendarEvent::TYPE_VACANCES], 'vacances'))
            ->merge($this->studentBirthdays($user))
            ->merge($this->personalBirthdays($user));
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    private function logbookEntries(User $user): Collection
    {
        return LogbookEntry::query()
            ->where('user_id', $user->id)
            // withTrashed(): a séance's class may since have been archived —
            // the calendar still shows the historical record correctly
            // rather than erroring or blanking out its title.
            ->with(['schoolClass' => fn ($query) => $query->withTrashed(), 'subject', 'progressionSequence', 'ecoleDirecteEvent'])
            ->get()
            ->map(function (LogbookEntry $entry) {
                $description = collect([
                    $entry->content,
                    $entry->homework ? 'Devoirs : '.$entry->homework : null,
                    $entry->progressionSequence ? 'Séquence : '.$entry->progressionSequence->title : null,
                ])->filter()->implode("\n\n");

                if ($entry->ecoleDirecteEvent) {
                    $startsAt = $entry->ecoleDirecteEvent->starts_at;
                    $endsAt = $entry->ecoleDirecteEvent->ends_at ?? $startsAt->copy()->addHour();
                    $allDay = false;
                } else {
                    $startsAt = $entry->date->copy()->startOfDay();
                    $endsAt = null;
                    $allDay = true;
                }

                return new CalendarItem(
                    uid: 'logbook-'.$entry->id,
                    type: 'cours',
                    title: ($entry->schoolClass?->name ?? 'Classe supprimée').($entry->subject ? ' — '.$entry->subject->name : ''),
                    description: $description === '' ? null : $description,
                    url: LogbookEntryResource::getUrl('edit', ['record' => $entry]),
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    allDay: $allDay,
                );
            });
    }

    /**
     * Raw Ecole-Directe occurrences not yet linked to a séance — shown on
     * the calendar (in a distinct, gray style, see
     * CalendarCategories::chipClasses()) as a reminder to link or ignore
     * them. A linked occurrence is deliberately excluded here: it's already
     * represented once via logbookEntries(), which uses its start/end time
     * when a séance is attached — showing it again here would duplicate it.
     *
     * @return Collection<int, CalendarItem>
     */
    private function ecoleDirecteEvents(User $user): Collection
    {
        return EcoleDirecteEvent::query()
            ->where('user_id', $user->id)
            ->whereDoesntHave('logbookEntry')
            ->get()
            ->map(fn (EcoleDirecteEvent $event) => new CalendarItem(
                uid: 'ecole-directe-'.$event->id,
                type: 'ecole_directe',
                title: $event->title,
                description: null,
                url: null,
                startsAt: $event->starts_at,
                endsAt: $event->ends_at,
                allDay: false,
            ));
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    private function studentEvents(User $user): Collection
    {
        return StudentEvent::query()
            ->where('user_id', $user->id)
            ->with('student')
            ->get()
            ->map(fn (StudentEvent $event) => new CalendarItem(
                uid: 'student-event-'.$event->id,
                type: 'reunions_eleves',
                title: $event->type.' — '.$event->student->full_name,
                description: $event->notes,
                url: StudentEventResource::getUrl('edit', ['record' => $event]),
                startsAt: $event->starts_at,
                endsAt: $event->ends_at,
                allDay: $event->all_day,
            ));
    }

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, CalendarItem>
     */
    private function calendarEvents(User $user, array $types, string $category): Collection
    {
        return CalendarEvent::query()
            ->where('user_id', $user->id)
            ->whereIn('type', $types)
            ->get()
            ->map(fn (CalendarEvent $event) => new CalendarItem(
                uid: 'calendar-event-'.$event->id,
                type: $category,
                title: $event->title,
                description: $event->notes,
                url: CalendarEventResource::getUrl('edit', ['record' => $event]),
                startsAt: $event->starts_at,
                endsAt: $event->ends_at,
                allDay: $event->all_day,
            ));
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    private function studentBirthdays(User $user): Collection
    {
        return Student::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->whereNotNull('birth_date')
            ->get()
            ->flatMap(fn (Student $student) => $this->yearlyBirthdayItems(
                uidPrefix: 'birthday-student-'.$student->id,
                type: 'anniversaires_eleves',
                title: 'Anniversaire — '.$student->full_name,
                date: $student->birth_date,
                studentId: $student->id,
            ));
    }

    /**
     * @return Collection<int, CalendarItem>
     */
    private function personalBirthdays(User $user): Collection
    {
        return PersonalBirthday::query()
            ->where('user_id', $user->id)
            ->get()
            ->flatMap(fn (PersonalBirthday $birthday) => $this->yearlyBirthdayItems(
                uidPrefix: 'birthday-personal-'.$birthday->id,
                type: 'anniversaires_personnels',
                title: 'Anniversaire — '.$birthday->name,
                date: $birthday->date,
                description: $birthday->notes,
            ));
    }

    /**
     * @return array<int, CalendarItem>
     */
    private function yearlyBirthdayItems(string $uidPrefix, string $type, string $title, Carbon $date, ?string $description = null, ?int $studentId = null): array
    {
        $currentYear = Carbon::today()->year;
        $items = [];

        for ($i = 0; $i < self::BIRTHDAY_YEARS_AHEAD; $i++) {
            $year = $currentYear + $i;

            $items[] = new CalendarItem(
                uid: $uidPrefix.'-'.$year,
                type: $type,
                title: $title,
                description: $description,
                url: $studentId ? null : CalendrierReglages::getUrl(),
                startsAt: $date->copy()->setYear($year)->startOfDay(),
                endsAt: null,
                allDay: true,
                studentId: $studentId,
            );
        }

        return $items;
    }
}
