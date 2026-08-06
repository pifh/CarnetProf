<?php

use App\Filament\Resources\StudentEvents\StudentEventResource;
use App\Livewire\StudentEventDetailModal;
use App\Livewire\StudentRecordCard;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\StudentEventAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('opens with the event loaded once the open-student-event-detail event fires', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $event = StudentEvent::factory()->for($teacher)->for($student)->create([
        'type' => 'Retard',
        'notes' => 'Ceci est un **test**.',
    ]);

    $this->actingAs($teacher);

    $component = Livewire::test(StudentEventDetailModal::class)
        ->call('open', $event->id);

    expect($component->get('eventId'))->toBe($event->id)
        ->and($component->get('event')->id)->toBe($event->id);

    $component->assertSee('Retard')
        ->assertSeeHtml(StudentEventResource::getUrl('edit', ['record' => $event]));
});

it('closes and stops rendering the event', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $event = StudentEvent::factory()->for($teacher)->for($student)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(StudentEventDetailModal::class)
        ->call('open', $event->id)
        ->call('close');

    expect($component->get('eventId'))->toBeNull()
        ->and($component->get('event'))->toBeNull();
});

it("refuses to load another teacher's event", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $otherEvent = StudentEvent::factory()->for($otherTeacher)->for($otherStudent)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(StudentEventDetailModal::class)
        ->call('open', $otherEvent->id);

    expect($component->get('event'))->toBeNull();
});

it('shows a download link for each attachment', function () {
    Storage::fake('public');

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $event = StudentEvent::factory()->for($teacher)->for($student)->create();
    $attachment = StudentEventAttachment::factory()->for($event, 'studentEvent')->create([
        'path' => 'student-event-attachments/justificatif.pdf',
        'original_filename' => 'justificatif.pdf',
    ]);

    $this->actingAs($teacher);

    Livewire::test(StudentEventDetailModal::class)
        ->call('open', $event->id)
        ->assertSee('justificatif.pdf')
        ->assertSeeHtml(Storage::disk('public')->url($attachment->path));
});

it('dispatches open-student-event-detail when an event is clicked in the fiche élève history', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $event = StudentEvent::factory()->for($teacher)->for($student)->create();

    $this->actingAs($teacher);

    Livewire::test(StudentRecordCard::class, ['studentId' => $student->id])
        ->assertSeeHtml("open-student-event-detail', { eventId: {$event->id} }");
});
