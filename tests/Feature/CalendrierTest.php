<?php

use App\Filament\Pages\Calendrier;
use App\Models\CalendarEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('defaults to today in month mode', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 4));
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(Calendrier::class)
        ->assertSet('mode', 'month')
        ->assertSet('cursor', '2026-08-04');

    Carbon::setTestNow();
});

it('moves the cursor by month, week or day depending on the active mode', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(Calendrier::class)
        ->set('mode', 'month')
        ->set('cursor', '2026-08-04')
        ->call('next')
        ->assertSet('cursor', '2026-09-04')
        ->call('previous')
        ->assertSet('cursor', '2026-08-04');

    Livewire::test(Calendrier::class)
        ->set('mode', 'week')
        ->set('cursor', '2026-08-04')
        ->call('next')
        ->assertSet('cursor', '2026-08-11');

    Livewire::test(Calendrier::class)
        ->set('mode', 'day')
        ->set('cursor', '2026-08-04')
        ->call('next')
        ->assertSet('cursor', '2026-08-05');
});

it('jumps to today from any position', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 4));
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(Calendrier::class)
        ->set('cursor', '2020-01-01')
        ->call('today')
        ->assertSet('cursor', '2026-08-04');

    Carbon::setTestNow();
});

it('switches to day mode on a given date via goToDay', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(Calendrier::class)
        ->set('mode', 'month')
        ->call('goToDay', '2026-08-12')
        ->assertSet('mode', 'day')
        ->assertSet('cursor', '2026-08-12');
});

it('places calendar items on the right cell of the month grid', function () {
    $teacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_ETABLISSEMENT,
        'title' => 'Portes ouvertes',
        'all_day' => true,
        'starts_at' => '2026-08-12',
        'ends_at' => null,
    ]);
    $this->actingAs($teacher);

    $component = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04');

    $items = $component->instance()->itemsFor(Carbon::parse('2026-08-12'));

    expect($items->pluck('uid'))->toContain('calendar-event-'.$event->id);
});

it('spans a multi-day vacation event across every day it covers in the month view', function () {
    $teacher = User::factory()->create();
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_VACANCES,
        'all_day' => true,
        'starts_at' => '2026-08-07',
        'ends_at' => '2026-08-10',
    ]);
    $this->actingAs($teacher);

    $component = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04');

    foreach (['2026-08-07', '2026-08-08', '2026-08-09', '2026-08-10'] as $date) {
        expect($component->instance()->itemsFor(Carbon::parse($date)))->toHaveCount(1);
    }

    expect($component->instance()->itemsFor(Carbon::parse('2026-08-11')))->toHaveCount(0);
});

it("only shows a teacher's own calendar items", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    CalendarEvent::factory()->for($teacher)->create(['starts_at' => '2026-08-12', 'all_day' => true]);
    CalendarEvent::factory()->for($otherTeacher)->create(['starts_at' => '2026-08-12', 'all_day' => true]);

    $this->actingAs($teacher);

    $component = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04');

    expect($component->instance()->itemsFor(Carbon::parse('2026-08-12')))->toHaveCount(1);
});

it('hides a category the teacher opted out of via calendar_display_categories, without affecting the ICS feed setting', function () {
    Carbon::setTestNow(Carbon::create(2026, 8, 12));

    $teacher = User::factory()->create([
        'calendar_display_categories' => ['etablissement'],
    ]);
    Student::factory()->for($teacher)->create(['birth_date' => '2012-08-12', 'is_archived' => false]);
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_ETABLISSEMENT,
        'all_day' => true,
        'starts_at' => '2026-08-12',
    ]);
    $this->actingAs($teacher);

    $component = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04');
    $items = $component->instance()->itemsFor(Carbon::parse('2026-08-12'));

    expect($items->pluck('type')->all())->toBe(['etablissement'])
        ->and($items->pluck('type'))->not->toContain('anniversaires_eleves');

    // Untouched: the feed setting stays at its own default (everything).
    expect($teacher->calendarFeedCategoriesOrDefault())->toContain('anniversaires_eleves');

    Carbon::setTestNow();
});

it('gives a timed item a top/height proportional to its start time and duration in the day timeline', function () {
    $teacher = User::factory()->create();
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV,
        'all_day' => false,
        'starts_at' => '2026-08-04 09:00:00',
        'ends_at' => '2026-08-04 10:00:00',
    ]);
    $this->actingAs($teacher);

    $timeline = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04')->instance()->dayTimeline;

    expect($timeline['startHour'])->toBe(7);
    $block = $timeline['days'][0]['blocks']->first();
    expect($block['top'])->toBe((9 - 7) * 48.0)
        ->and($block['height'])->toBe(48.0)
        ->and($block['width'])->toBe(100.0);
});

it('places two overlapping timed items side by side, splitting the available width', function () {
    $teacher = User::factory()->create();
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV, 'all_day' => false,
        'starts_at' => '2026-08-04 09:00:00', 'ends_at' => '2026-08-04 10:00:00',
    ]);
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_REUNION, 'all_day' => false,
        'starts_at' => '2026-08-04 09:30:00', 'ends_at' => '2026-08-04 10:30:00',
    ]);
    $this->actingAs($teacher);

    $timeline = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04')->instance()->dayTimeline;
    $blocks = $timeline['days'][0]['blocks'];

    expect($blocks)->toHaveCount(2);
    expect($blocks->pluck('width')->all())->toBe([50.0, 50.0]);
    expect($blocks->pluck('left')->sort()->values()->all())->toBe([0.0, 50.0]);
});

it('gives an open-ended timed item a default 30-minute visual duration', function () {
    $teacher = User::factory()->create();
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_REUNION,
        'all_day' => false,
        'starts_at' => '2026-08-04 09:00:00',
        'ends_at' => null,
    ]);
    $this->actingAs($teacher);

    $timeline = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04')->instance()->dayTimeline;

    expect($timeline['days'][0]['blocks']->first()['height'])->toBe(24.0);
});

it('extends the timeline hour range to fit items outside the default 7h-19h window', function () {
    $teacher = User::factory()->create();
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV, 'all_day' => false,
        'starts_at' => '2026-08-04 06:00:00', 'ends_at' => '2026-08-04 06:30:00',
    ]);
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV, 'all_day' => false,
        'starts_at' => '2026-08-04 20:00:00', 'ends_at' => '2026-08-04 21:00:00',
    ]);
    $this->actingAs($teacher);

    $timeline = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04')->instance()->dayTimeline;

    expect($timeline['startHour'])->toBe(6)
        ->and($timeline['endHour'])->toBe(21);
});

it('shares a single hour range across every day of the week timeline', function () {
    $teacher = User::factory()->create();
    CalendarEvent::factory()->for($teacher)->create([
        'type' => CalendarEvent::TYPE_RDV, 'all_day' => false,
        'starts_at' => '2026-08-06 20:00:00', 'ends_at' => '2026-08-06 21:00:00',
    ]);
    $this->actingAs($teacher);

    $timeline = Livewire::test(Calendrier::class)->set('cursor', '2026-08-04')->instance()->weekTimeline;

    expect($timeline['endHour'])->toBe(21)
        ->and($timeline['days'])->toHaveCount(7);
});
