<?php

use App\Filament\Pages\Archives;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

it('lists archived classes with their student count', function () {
    $teacher = User::factory()->create();
    $activeClass = SchoolClass::factory()->for($teacher)->create();
    $archivedClass = SchoolClass::factory()->for($teacher)->archived()->create();
    Student::factory()->for($teacher)->for($archivedClass, 'schoolClass')->archived()->count(2)->create();

    $this->actingAs($teacher);

    $classes = Livewire::test(Archives::class)->get('archivedClasses');

    expect($classes)->toHaveCount(1)
        ->and($classes->first()->id)->toBe($archivedClass->id)
        ->and($classes->first()->students_count)->toBe(2);
});

it('lists students archived individually while their class stays active', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $archivedStudent = Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $archivedClass = SchoolClass::factory()->for($teacher)->archived()->create();
    $studentInArchivedClass = Student::factory()->for($teacher)->for($archivedClass, 'schoolClass')->archived()->create();

    $this->actingAs($teacher);

    $students = Livewire::test(Archives::class)->get('archivedStudents');

    expect($students->pluck('id')->all())->toBe([$archivedStudent->id])
        ->and($students->pluck('id')->all())->not->toContain($studentInArchivedClass->id);
});

it('unarchives a class from the Archives page', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->archived()->create();

    $this->actingAs($teacher);

    Livewire::test(Archives::class)->call('unarchiveClass', $class->id);

    expect($class->refresh()->is_archived)->toBeFalse();
});

it('unarchives a student from the Archives page', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create();

    $this->actingAs($teacher);

    Livewire::test(Archives::class)->call('unarchiveStudent', $student->id);

    expect($student->refresh()->is_archived)->toBeFalse();
});

it("keeps the Archives page isolated to the current teacher's data", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->archived()->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->archived()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(Archives::class);

    expect($component->get('archivedClasses'))->toHaveCount(0)
        ->and($component->get('archivedStudents'))->toHaveCount(0);

    // Attempting to unarchive another teacher's class must not succeed.
    $component->call('unarchiveClass', $otherClass->id);
    expect($otherClass->refresh()->is_archived)->toBeTrue();
});
