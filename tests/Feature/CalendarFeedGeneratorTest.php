<?php

use App\Models\CalendarEvent;
use App\Models\EcoleDirecteEvent;
use App\Models\LogbookEntry;
use App\Models\PersonalBirthday;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\User;
use App\Services\CalendarFeedGenerator;
use Illuminate\Support\Carbon;
use Sabre\VObject\Reader;

it('emits one VEVENT per source, tagged with the right category and url', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $edEvent = EcoleDirecteEvent::factory()->for($teacher)->create([
        'starts_at' => '2026-09-10 08:00:00',
        'ends_at' => '2026-09-10 08:55:00',
    ]);
    $logbookEntry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')
        ->create(['ecole_directe_event_id' => $edEvent->id, 'content' => 'Chapitre 3']);

    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentEvent = StudentEvent::factory()->for($teacher)->for($student)->create(['type' => 'Réunion parents', 'notes' => 'RAS']);

    $calendarEvent = CalendarEvent::factory()->for($teacher)->create(['type' => CalendarEvent::TYPE_ETABLISSEMENT, 'title' => 'Portes ouvertes']);

    $calendar = app(CalendarFeedGenerator::class)->generateForUser($teacher);
    $parsed = Reader::read($calendar->serialize());

    $summaries = collect($parsed->VEVENT)->map(fn ($v) => (string) $v->SUMMARY)->all();

    expect($summaries)->toContain($class->name)
        ->and($summaries)->toContain('Réunion parents — '.$student->full_name)
        ->and($summaries)->toContain('Portes ouvertes');

    $logbookVevent = collect($parsed->VEVENT)->first(fn ($v) => (string) $v->UID === 'logbook-'.$logbookEntry->id.'@carnetprof');
    expect((string) $logbookVevent->CATEGORIES)->toBe('cours')
        ->and((string) $logbookVevent->DTSTART)->toStartWith('20260910T080000');
});

it('respects disabled categories', function () {
    $teacher = User::factory()->create(['calendar_feed_categories' => ['reunions_rdv']]);
    $class = SchoolClass::factory()->for($teacher)->create();
    LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create();
    CalendarEvent::factory()->for($teacher)->create(['type' => CalendarEvent::TYPE_REUNION]);

    $calendar = app(CalendarFeedGenerator::class)->generateForUser($teacher);
    $parsed = Reader::read($calendar->serialize());

    $categories = collect($parsed->VEVENT ?? [])->map(fn ($v) => (string) $v->CATEGORIES)->all();

    expect($categories)->toContain('reunions_rdv')
        ->and($categories)->not->toContain('cours');
});

it('materializes yearly-recurring birthdays for the next few years', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 4));

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['birth_date' => '2012-08-10', 'is_archived' => false]);
    PersonalBirthday::factory()->for($teacher)->create(['name' => 'Marie', 'date' => '1980-08-20']);

    $calendar = app(CalendarFeedGenerator::class)->generateForUser($teacher);
    $parsed = Reader::read($calendar->serialize());

    $studentBirthdays = collect($parsed->VEVENT)->filter(fn ($v) => str_starts_with((string) $v->UID, 'birthday-student-'));
    $personalBirthdays = collect($parsed->VEVENT)->filter(fn ($v) => str_starts_with((string) $v->UID, 'birthday-personal-'));

    expect($studentBirthdays)->toHaveCount(3)
        ->and($personalBirthdays)->toHaveCount(3);

    Carbon::setTestNow();
});
