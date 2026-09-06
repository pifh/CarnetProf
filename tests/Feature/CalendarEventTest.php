<?php

use App\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\EditCalendarEvent;
use App\Filament\Resources\CalendarEvents\Pages\ListCalendarEvents;
use App\Models\CalendarEvent;
use App\Models\CalendarEventAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates an all-day calendar event stamped with the teacher\'s id', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEvent::TYPE_RDV,
            'title' => 'Rendez-vous administration',
            'all_day' => true,
            'starts_at' => '2026-09-20',
            'notes' => "## Ordre du jour\n\n- Point 1",
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::query()->where('title', 'Rendez-vous administration')->sole();

    expect($event->user_id)->toBe($teacher->id)
        ->and($event->type)->toBe(CalendarEvent::TYPE_RDV)
        ->and($event->all_day)->toBeTrue()
        ->and($event->starts_at->format('Y-m-d'))->toBe('2026-09-20')
        ->and($event->notes)->toBe("## Ordre du jour\n\n- Point 1");
});

it('creates a multi-day all-day event with an end date, for vacances', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEvent::TYPE_VACANCES,
            'title' => 'Vacances de la Toussaint',
            'all_day' => true,
            'starts_at' => '2026-10-17',
            'ends_at' => '2026-11-02',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::query()->where('title', 'Vacances de la Toussaint')->sole();

    expect($event->starts_at->format('Y-m-d'))->toBe('2026-10-17')
        ->and($event->ends_at->format('Y-m-d'))->toBe('2026-11-02');
});

it('creates a DST (devoir surveillé) calendar event', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEvent::TYPE_DST,
            'title' => 'DST de mathématiques',
            'all_day' => true,
            'starts_at' => '2026-09-25',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::query()->where('title', 'DST de mathématiques')->sole();

    expect($event->type)->toBe(CalendarEvent::TYPE_DST);
});

it('creates a timed calendar event with a start and end time', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CreateCalendarEvent::class)
        ->fillForm([
            'type' => CalendarEvent::TYPE_REUNION,
            'title' => 'Conseil de classe',
            'all_day' => false,
            'starts_at' => '2026-09-20 17:00',
            'ends_at' => '2026-09-20 19:00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = CalendarEvent::query()->where('title', 'Conseil de classe')->sole();

    expect($event->all_day)->toBeFalse()
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2026-09-20 17:00')
        ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2026-09-20 19:00');
});

it('lets a teacher edit and delete their own calendar event', function () {
    $teacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($teacher)->create(['title' => 'Ancien titre']);
    $this->actingAs($teacher);

    Livewire::test(EditCalendarEvent::class, ['record' => $event->getKey()])
        ->fillForm(['title' => 'Nouveau titre'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->title)->toBe('Nouveau titre');

    Livewire::test(EditCalendarEvent::class, ['record' => $event->getKey()])
        ->callAction('delete');

    expect(CalendarEvent::find($event->id))->toBeNull();
});

it("prevents a teacher from updating or deleting another teacher's calendar event", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $event))->toBeFalse()
        ->and($teacher->can('delete', $event))->toBeFalse();
});

it("only lists a teacher's own calendar events", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $own = CalendarEvent::factory()->for($teacher)->create();
    CalendarEvent::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ListCalendarEvents::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCountTableRecords(1);
});

it('attaches an uploaded file to a calendar event and stores its original filename', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($teacher)->create();

    $file = UploadedFile::fake()->image('compte-rendu.jpg');
    $path = $file->store('calendar-events', 'public');

    $attachment = CalendarEventAttachment::create([
        'calendar_event_id' => $event->id,
        'path' => $path,
        'original_filename' => $file->getClientOriginalName(),
    ]);

    expect($attachment->original_filename)->toBe('compte-rendu.jpg')
        ->and($event->attachments()->sole()->id)->toBe($attachment->id);

    Storage::disk('public')->assertExists($path);
});

it('deletes all attachment files from disk when the event itself is deleted', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $event = CalendarEvent::factory()->for($teacher)->create();
    $first = CalendarEventAttachment::factory()->for($event, 'calendarEvent')->create(['path' => 'calendar-events/a.jpg']);
    $second = CalendarEventAttachment::factory()->for($event, 'calendarEvent')->create(['path' => 'calendar-events/b.mp3']);
    Storage::disk('public')->put($first->path, 'fake-content');
    Storage::disk('public')->put($second->path, 'fake-content');

    $this->actingAs($teacher);

    Livewire::test(EditCalendarEvent::class, ['record' => $event->getKey()])
        ->callAction('delete');

    expect(CalendarEvent::query()->find($event->id))->toBeNull()
        ->and(CalendarEventAttachment::query()->where('calendar_event_id', $event->id)->count())->toBe(0);

    Storage::disk('public')->assertMissing('calendar-events/a.jpg');
    Storage::disk('public')->assertMissing('calendar-events/b.mp3');
});
