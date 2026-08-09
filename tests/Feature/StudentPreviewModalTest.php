<?php

use App\Filament\Pages\GradeTracking;
use App\Filament\Pages\Trombinoscope;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Widgets\TodaysBirthdays;
use App\Livewire\StudentPreviewModal;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('opens with the student loaded once the open-student-preview event fires', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'first_name' => 'Alice',
        'last_name' => 'Aaronson',
    ]);

    $this->actingAs($teacher);

    $component = Livewire::test(StudentPreviewModal::class)
        ->call('open', $student->id);

    expect($component->get('studentId'))->toBe($student->id)
        ->and($component->get('student')->id)->toBe($student->id);

    $component->assertSee('Alice Aaronson')
        ->assertSee($class->name)
        ->assertSeeHtml(StudentResource::getUrl('edit', ['record' => $student]));
});

it('closes and stops rendering the student', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(StudentPreviewModal::class)
        ->call('open', $student->id)
        ->call('close');

    expect($component->get('studentId'))->toBeNull()
        ->and($component->get('student'))->toBeNull();
});

it("refuses to load another teacher's student", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(StudentPreviewModal::class)
        ->call('open', $otherStudent->id);

    expect($component->get('student'))->toBeNull();
});

it('dispatches open-student-preview when a student\'s name is clicked on the Trombinoscope grid', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(Trombinoscope::class)
        ->set('schoolClassId', $class->id)
        ->assertSeeHtml("open-student-preview', { studentId: {$student->id} }");
});

it('dispatches open-student-preview when a student row is clicked on the grade tracking roster', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $class->subjects()->attach(Subject::factory()->for($teacher)->create());
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->assertSeeHtml("open-student-preview', { studentId: {$student->id} }");
});

it('dispatches open-student-preview when a name or photo is clicked in the dashboard birthdays widget', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'birth_date' => Carbon::today()->subYears(10),
    ]);

    $this->actingAs($teacher);

    Livewire::test(TodaysBirthdays::class)
        ->callTableColumnAction('full_name', $student)
        ->assertDispatched('open-student-preview', studentId: $student->id);

    Livewire::test(TodaysBirthdays::class)
        ->callTableColumnAction('photo_url', $student)
        ->assertDispatched('open-student-preview', studentId: $student->id);
});
