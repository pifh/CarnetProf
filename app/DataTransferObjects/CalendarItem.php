<?php

namespace App\DataTransferObjects;

use Illuminate\Support\Carbon;

/**
 * A single displayable calendar entry, shared by the in-app day/week/month
 * views (App\Filament\Pages\Calendrier) and the ICS feed generator
 * (App\Services\CalendarFeedGenerator) — both consume
 * App\Services\CalendarItemCollector rather than querying each source
 * separately.
 */
final readonly class CalendarItem
{
    public function __construct(
        public string $uid,
        public string $type,
        public string $title,
        public ?string $description,
        public ?string $url,
        public Carbon $startsAt,
        public ?Carbon $endsAt,
        public bool $allDay,
        public ?int $studentId = null,
    ) {}

    /**
     * Every calendar date (Y-m-d) this item occupies. A single date for
     * timed/point-in-time items; every day in [startsAt, endsAt] inclusive
     * for all-day items with a range (e.g. a multi-day vacances period), so
     * the day/week/month grids can show it on each day it spans.
     *
     * @return array<int, string>
     */
    public function datesOccupied(): array
    {
        if (! $this->allDay || ! $this->endsAt || $this->endsAt->isSameDay($this->startsAt)) {
            return [$this->startsAt->format('Y-m-d')];
        }

        $dates = [];
        $cursor = $this->startsAt->copy()->startOfDay();
        $end = $this->endsAt->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $dates;
    }
}
