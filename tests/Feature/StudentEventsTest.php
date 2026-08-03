<?php

use App\Filament\Resources\StudentEvents\Pages\CreateStudentEvent;
use App\Filament\Resources\StudentEvents\Pages\EditStudentEvent;
use App\Filament\Resources\StudentEvents\Pages\ListStudentEvents;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\StudentEventAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('creates a student event stamped with the teacher\'s id', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(CreateStudentEvent::class)
        ->fillForm([
            'student_id' => $student->id,
            'type' => 'Réunion parents',
            'event_date' => '2026-05-10',
            'notes' => 'Discussion sur les progrès en mathématiques.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = StudentEvent::query()->where('student_id', $student->id)->sole();

    expect($event->type)->toBe('Réunion parents')
        ->and($event->notes)->toBe('Discussion sur les progrès en mathématiques.')
        ->and($event->user_id)->toBe($teacher->id);
});

it('attaches an uploaded file to an event and stores its original filename', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $event = StudentEvent::factory()->for($teacher)->create();

    $file = UploadedFile::fake()->image('compte-rendu.jpg');
    $path = $file->store('student-events', 'public');

    $attachment = StudentEventAttachment::create([
        'student_event_id' => $event->id,
        'path' => $path,
        'original_filename' => $file->getClientOriginalName(),
    ]);

    expect($attachment->original_filename)->toBe('compte-rendu.jpg')
        ->and($event->attachments()->sole()->id)->toBe($attachment->id);

    Storage::disk('public')->assertExists($path);
});

it('lists suggested event types from a teacher\'s own events only', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    StudentEvent::factory()->for($teacher)->create(['type' => 'Avertissement']);
    StudentEvent::factory()->for($teacher)->create(['type' => 'Réunion parents']);
    StudentEvent::factory()->for($otherTeacher)->create(['type' => 'Rencontre mensuelle']);

    $this->actingAs($teacher);

    expect(StudentEvent::allTypes())->toBe(['Avertissement', 'Réunion parents']);
});

it('only shows a teacher their own student events', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $ownEvent = StudentEvent::factory()->for($teacher)->create();
    StudentEvent::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ListStudentEvents::class)
        ->assertCanSeeTableRecords([$ownEvent])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's student event", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $event = StudentEvent::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $event))->toBeFalse()
        ->and($teacher->can('delete', $event))->toBeFalse();
});

it('deletes the attachment file from disk when the attachment is removed', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $event = StudentEvent::factory()->for($teacher)->create();
    $attachment = StudentEventAttachment::factory()->for($event, 'studentEvent')->create(['path' => 'student-events/report.jpg']);
    Storage::disk('public')->put($attachment->path, 'fake-content');

    $attachment->delete();

    Storage::disk('public')->assertMissing('student-events/report.jpg');
});

it('deletes all attachment files from disk when the event itself is deleted', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $event = StudentEvent::factory()->for($teacher)->create();
    $first = StudentEventAttachment::factory()->for($event, 'studentEvent')->create(['path' => 'student-events/a.jpg']);
    $second = StudentEventAttachment::factory()->for($event, 'studentEvent')->create(['path' => 'student-events/b.mp3']);
    Storage::disk('public')->put($first->path, 'fake-content');
    Storage::disk('public')->put($second->path, 'fake-content');

    $this->actingAs($teacher);

    Livewire::test(EditStudentEvent::class, ['record' => $event->getKey()])
        ->callAction('delete');

    expect(StudentEvent::query()->find($event->id))->toBeNull()
        ->and(StudentEventAttachment::query()->where('student_event_id', $event->id)->count())->toBe(0);

    Storage::disk('public')->assertMissing('student-events/a.jpg');
    Storage::disk('public')->assertMissing('student-events/b.mp3');
});
