<?php

use App\Models\CalendarEvent;
use App\Models\EcoleDirecteEvent;
use App\Models\LogbookEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\User;
use App\Services\CalendarItemCollector;
use Illuminate\Support\Carbon;

it('collects one CalendarItem per source, with the right type and url', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $logbookEntry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create(['date' => '2026-09-10']);

    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['is_archived' => false]);
    $studentEvent = StudentEvent::factory()->for($teacher)->for($student)->create();

    $calendarEvent = CalendarEvent::factory()->for($teacher)->create(['type' => CalendarEvent::TYPE_ETABLISSEMENT]);

    $items = app(CalendarItemCollector::class)->forUser($teacher);

    $logbookItem = $items->first(fn ($item) => $item->uid === 'logbook-'.$logbookEntry->id);
    expect($logbookItem->type)->toBe('cours')
        ->and($logbookItem->url)->toContain('logbook-entries/'.$logbookEntry->id);

    $studentEventItem = $items->first(fn ($item) => $item->uid === 'student-event-'.$studentEvent->id);
    expect($studentEventItem->type)->toBe('reunions_eleves')
        ->and($studentEventItem->url)->toContain('student-events/'.$studentEvent->id);

    $calendarEventItem = $items->first(fn ($item) => $item->uid === 'calendar-event-'.$calendarEvent->id);
    expect($calendarEventItem->type)->toBe('etablissement')
        ->and($calendarEventItem->url)->toContain('calendar-events/'.$calendarEvent->id);
});

it('links a séance to its Ecole-Directe slot and marks it timed rather than all-day', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $edEvent = EcoleDirecteEvent::factory()->for($teacher)->create([
        'starts_at' => '2026-09-10 08:00:00',
        'ends_at' => '2026-09-10 08:55:00',
    ]);
    $logbookEntry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')
        ->create(['ecole_directe_event_id' => $edEvent->id, 'date' => '2026-09-10']);

    $items = app(CalendarItemCollector::class)->forUser($teacher);
    $item = $items->first(fn ($item) => $item->uid === 'logbook-'.$logbookEntry->id);

    expect($item->allDay)->toBeFalse()
        ->and($item->startsAt->format('Y-m-d H:i'))->toBe('2026-09-10 08:00')
        ->and($item->datesOccupied())->toBe(['2026-09-10']);
});

it('marks a student birthday item with studentId instead of a url', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Carbon::setTestNow(Carbon::create(2026, 8, 4));

    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')
        ->create(['birth_date' => '2012-08-10', 'is_archived' => false]);

    $items = app(CalendarItemCollector::class)->forUser($teacher);
    $item = $items->first(fn ($item) => $item->uid === 'birthday-student-'.$student->id.'-2026');

    expect($item->type)->toBe('anniversaires_eleves')
        ->and($item->url)->toBeNull()
        ->and($item->studentId)->toBe($student->id);

    Carbon::setTestNow();
});

it('spans a multi-day all-day CalendarEvent across every date it covers', function () {
    $teacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_VACANCES,
        'all_day' => true,
        'starts_at' => '2026-08-07',
        'ends_at' => '2026-08-10',
    ]);

    $items = app(CalendarItemCollector::class)->forUser($teacher);
    $item = $items->first(fn ($item) => $item->uid === 'calendar-event-'.$event->id);

    expect($item->datesOccupied())->toBe(['2026-08-07', '2026-08-08', '2026-08-09', '2026-08-10']);
});

it('returns a single date for a punctual, non-ranged item', function () {
    $teacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV,
        'all_day' => false,
        'starts_at' => '2026-09-15 10:00:00',
        'ends_at' => null,
    ]);

    $items = app(CalendarItemCollector::class)->forUser($teacher);
    $item = $items->first(fn ($item) => $item->uid === 'calendar-event-'.$event->id);

    expect($item->datesOccupied())->toBe(['2026-09-15']);
});
