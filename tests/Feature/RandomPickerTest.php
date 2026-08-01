<?php

use App\Filament\Pages\RandomPicker;
use App\Models\RandomPick;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

it('picks a student and records the draw', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('pick');

    expect($component->get('lastPickedStudentId'))->toBe($student->id);

    $pick = RandomPick::query()->first();

    expect($pick->student_id)->toBe($student->id)
        ->and($pick->school_class_id)->toBe($class->id)
        ->and($pick->user_id)->toBe($teacher->id);
});

it('does not repeat a student until every eligible student has been picked', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)->set('schoolClassId', $class->id);

    $picked = [];
    foreach (range(1, 3) as $i) {
        $component->call('pick');
        $picked[] = $component->get('lastPickedStudentId');
    }

    expect(array_unique($picked))->toHaveCount(3)
        ->and($component->get('pickedStudentIdsThisRound'))->toHaveCount(3);

    $component->call('pick');

    expect($component->get('pickedStudentIdsThisRound'))->toHaveCount(1);
});

it('excludes marked students from the draw', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $absent = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $present = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleExcluded', $absent->id);

    foreach (range(1, 5) as $i) {
        $component->call('pick');
        expect($component->get('lastPickedStudentId'))->toBe($present->id);
    }
});

it('resets the round', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->call('pick')
        ->call('resetRound');

    expect($component->get('lastPickedStudentId'))->toBeNull()
        ->and($component->get('pickedStudentIdsThisRound'))->toBe([]);
});

it('aggregates a per-student pick count in the history', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    RandomPick::factory()->for($teacher)->for($studentA)->for($class, 'schoolClass')->count(3)->create();
    RandomPick::factory()->for($teacher)->for($studentB)->for($class, 'schoolClass')->count(1)->create();

    $this->actingAs($teacher);

    $history = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->get('history');

    expect($history->firstWhere('student.id', $studentA->id)['count'])->toBe(3)
        ->and($history->firstWhere('student.id', $studentB->id)['count'])->toBe(1);
});

it("prevents a teacher from updating or deleting another teacher's random pick", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $pick = RandomPick::factory()->for($otherTeacher)->for($otherStudent)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $pick))->toBeFalse()
        ->and($teacher->can('delete', $pick))->toBeFalse();
});

it("keeps the draw isolated from another teacher's students", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $students = Livewire::test(RandomPicker::class)
        ->set('schoolClassId', $class->id)
        ->get('students');

    expect($students)->toHaveCount(1)
        ->and($students->first()->id)->toBe($student->id);
});
