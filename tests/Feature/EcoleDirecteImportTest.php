<?php

use App\Models\EcoleDirecteEvent;
use App\Models\LogbookEntry;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\EcoleDirecteIcsImporter;
use Illuminate\Support\Facades\Http;

function fakeIcsFixture(): string
{
    return <<<'ICS'
    BEGIN:VCALENDAR
    VERSION:2.0
    PRODID:-//Ecole-Directe//Test//EN
    BEGIN:VEVENT
    UID:ed-event-1@ecole-directe
    SUMMARY:Mathématiques 6e A
    DTSTART:20260910T080000Z
    DTEND:20260910T085500Z
    END:VEVENT
    BEGIN:VEVENT
    UID:ed-event-2@ecole-directe
    SUMMARY:Français 5e B
    DTSTART:20260910T100000Z
    DTEND:20260910T105500Z
    END:VEVENT
    END:VCALENDAR
    ICS;
}

it('imports Ecole-Directe occurrences from the configured ICS URL', function () {
    $teacher = User::factory()->create(['ecole_directe_ics_url' => 'https://ecole-directe.test/calendar.ics']);

    Http::fake(['ecole-directe.test/*' => Http::response(fakeIcsFixture())]);

    $count = app(EcoleDirecteIcsImporter::class)->importForUser($teacher);

    expect($count)->toBe(2)
        ->and(EcoleDirecteEvent::withoutGlobalScopes()->where('user_id', $teacher->id)->count())->toBe(2);

    $event = EcoleDirecteEvent::withoutGlobalScopes()->where('uid', 'ed-event-1@ecole-directe')->first();
    expect($event->title)->toBe('Mathématiques 6e A');

    $teacher->refresh();
    expect($teacher->ecole_directe_synced_at)->not->toBeNull();
});

it('updates existing occurrences by uid instead of duplicating them', function () {
    $teacher = User::factory()->create(['ecole_directe_ics_url' => 'https://ecole-directe.test/calendar.ics']);
    $updatedFixture = str_replace('Mathématiques 6e A', 'Mathématiques 6e A (salle 12)', fakeIcsFixture());

    Http::fake([
        'ecole-directe.test/*' => Http::sequence()
            ->push(fakeIcsFixture())
            ->push($updatedFixture),
    ]);

    app(EcoleDirecteIcsImporter::class)->importForUser($teacher);
    app(EcoleDirecteIcsImporter::class)->importForUser($teacher);

    expect(EcoleDirecteEvent::withoutGlobalScopes()->where('user_id', $teacher->id)->count())->toBe(2);

    $event = EcoleDirecteEvent::withoutGlobalScopes()->where('uid', 'ed-event-1@ecole-directe')->first();
    expect($event->title)->toBe('Mathématiques 6e A (salle 12)');
});

it("never imports one teacher's occurrences into another teacher's cache", function () {
    $teacherA = User::factory()->create(['ecole_directe_ics_url' => 'https://ecole-directe.test/calendar.ics']);
    $teacherB = User::factory()->create();

    Http::fake(['ecole-directe.test/*' => Http::response(fakeIcsFixture())]);
    app(EcoleDirecteIcsImporter::class)->importForUser($teacherA);

    expect(EcoleDirecteEvent::withoutGlobalScopes()->where('user_id', $teacherB->id)->count())->toBe(0);
});

it('does nothing when no Ecole-Directe URL is configured', function () {
    $teacher = User::factory()->create();

    $count = app(EcoleDirecteIcsImporter::class)->importForUser($teacher);

    expect($count)->toBe(0)
        ->and(EcoleDirecteEvent::withoutGlobalScopes()->where('user_id', $teacher->id)->count())->toBe(0);
});

it("detaches (without deleting) a séance's link when its occurrence disappears from the synced schedule", function () {
    $teacher = User::factory()->create(['ecole_directe_ics_url' => 'https://ecole-directe.test/calendar.ics']);
    $class = SchoolClass::factory()->for($teacher)->create();

    $shrunkFixture = <<<'ICS'
    BEGIN:VCALENDAR
    VERSION:2.0
    PRODID:-//Ecole-Directe//Test//EN
    BEGIN:VEVENT
    UID:ed-event-2@ecole-directe
    SUMMARY:Français 5e B
    DTSTART:20260910T100000Z
    DTEND:20260910T105500Z
    END:VEVENT
    END:VCALENDAR
    ICS;

    Http::fake([
        'ecole-directe.test/*' => Http::sequence()
            ->push(fakeIcsFixture())
            ->push($shrunkFixture),
    ]);

    app(EcoleDirecteIcsImporter::class)->importForUser($teacher);

    $event = EcoleDirecteEvent::withoutGlobalScopes()->where('uid', 'ed-event-1@ecole-directe')->first();
    $entry = LogbookEntry::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'ecole_directe_event_id' => $event->id,
        'content' => 'Contenu original',
    ]);

    app(EcoleDirecteIcsImporter::class)->importForUser($teacher);

    expect(EcoleDirecteEvent::withoutGlobalScopes()->where('uid', 'ed-event-1@ecole-directe')->exists())->toBeFalse();

    $entry->refresh();
    expect($entry->ecole_directe_event_id)->toBeNull()
        ->and($entry->content)->toBe('Contenu original');
});

it('does not delete cached occurrences when the feed comes back empty', function () {
    $teacher = User::factory()->create(['ecole_directe_ics_url' => 'https://ecole-directe.test/calendar.ics']);

    $emptyFixture = <<<'ICS'
    BEGIN:VCALENDAR
    VERSION:2.0
    PRODID:-//Ecole-Directe//Test//EN
    END:VCALENDAR
    ICS;

    Http::fake([
        'ecole-directe.test/*' => Http::sequence()
            ->push(fakeIcsFixture())
            ->push($emptyFixture),
    ]);

    app(EcoleDirecteIcsImporter::class)->importForUser($teacher);
    app(EcoleDirecteIcsImporter::class)->importForUser($teacher);

    expect(EcoleDirecteEvent::withoutGlobalScopes()->where('user_id', $teacher->id)->count())->toBe(2);
});
