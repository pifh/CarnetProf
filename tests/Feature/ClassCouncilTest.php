<?php

use App\Filament\Pages\ClassCouncil;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

it('defaults to the first active class, first term, and first student', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['name' => 'A - 6e A']);
    $class->subjects()->attach(Subject::factory()->for($teacher)->create(['name' => 'Matière A']));
    $otherClass = SchoolClass::factory()->for($teacher)->create(['name' => 'B - 5e B']);
    $otherClass->subjects()->attach(Subject::factory()->for($teacher)->create(['name' => 'Matière B']));
    $term = Term::factory()->for($teacher)->create(['position' => 1]);
    Term::factory()->for($teacher)->create(['position' => 2]);
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Aaronson']);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Zorro']);
    $this->actingAs($teacher);

    $component = Livewire::test(ClassCouncil::class);

    expect($component->get('schoolClassId'))->toBe($class->id)
        ->and($component->get('termId'))->toBe($term->id)
        ->and($component->get('selectedStudentId'))->toBe($studentA->id);
});

it('lets a teacher select a student from the list', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(ClassCouncil::class)->call('selectStudent', $studentB->id);

    expect($component->get('selectedStudentId'))->toBe($studentB->id);
});

it('reselects the first student of the new class when switching classes', function () {
    $teacher = User::factory()->create();
    $classA = SchoolClass::factory()->for($teacher)->create();
    $classA->subjects()->attach(Subject::factory()->for($teacher)->create(['name' => 'Matière A']));
    $classB = SchoolClass::factory()->for($teacher)->create();
    $classB->subjects()->attach(Subject::factory()->for($teacher)->create(['name' => 'Matière B']));
    Student::factory()->for($teacher)->for($classA, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($classB, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(ClassCouncil::class)->set('schoolClassId', $classB->id);

    expect($component->get('selectedStudentId'))->toBe($studentB->id);
});

it('includes a groupe classe member in the student list', function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass->subjects()->attach(Subject::factory()->for($teacher)->create());
    $borrowedStudent = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $borrowedStudent->groupClasses()->attach($groupClass);
    $this->actingAs($teacher);

    $component = Livewire::test(ClassCouncil::class)->set('schoolClassId', $groupClass->id);

    expect($component->get('students')->pluck('id'))->toContain($borrowedStudent->id);
});
