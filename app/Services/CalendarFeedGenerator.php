<?php

namespace App\Services;

use App\DataTransferObjects\CalendarItem;
use App\Models\User;
use Sabre\VObject\Component\VCalendar;

/**
 * Turns a teacher's CalendarItem collection (from CalendarItemCollector)
 * into a VCALENDAR for the public ICS subscription endpoint. Runs both in
 * authenticated (Calendrier page) and unauthenticated (public ICS endpoint)
 * contexts — CalendarItemCollector already scopes every query explicitly to
 * $user->id rather than relying on BelongsToTeacher's auth()-based global
 * scope, so this class has nothing auth-dependent left to worry about.
 */
class CalendarFeedGenerator
{
    public function __construct(private readonly CalendarItemCollector $collector) {}

    public function generateForUser(User $user): VCalendar
    {
        $calendar = new VCalendar;
        $calendar->METHOD = 'PUBLISH';

        $categories = $user->calendarFeedCategoriesOrDefault();

        $this->collector->forUser($user)
            ->filter(fn (CalendarItem $item) => in_array($item->type, $categories, true))
            ->each(function (CalendarItem $item) use ($calendar) {
                $vevent = $calendar->add('VEVENT');
                $vevent->UID = $item->uid.'@carnetprof';
                $vevent->SUMMARY = $item->title;
                $vevent->CATEGORIES = $item->type;

                if ($item->description) {
                    $vevent->DESCRIPTION = $item->description;
                }

                if ($item->url) {
                    $vevent->URL = $item->url;
                }

                if ($item->allDay) {
                    $vevent->add('DTSTART', $item->startsAt->format('Ymd'), ['VALUE' => 'DATE']);

                    if ($item->endsAt) {
                        $vevent->add('DTEND', $item->endsAt->format('Ymd'), ['VALUE' => 'DATE']);
                    }
                } else {
                    $vevent->DTSTART = $item->startsAt->format('Ymd\THis');

                    if ($item->endsAt) {
                        $vevent->DTEND = $item->endsAt->format('Ymd\THis');
                    }
                }
            });

        return $calendar;
    }
}
