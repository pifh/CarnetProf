<?php

use App\Filament\Pages\GroupGenerator;
use App\Filament\Resources\StudentSubgroups\Pages\ListStudentSubgroups;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use App\Models\User;
use Livewire\Livewire;

it("only lists a teacher's own work groups", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $class = SchoolClass::factory()->for($teacher)->create();
    $ownGroup = StudentSubgroup::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    StudentSubgroup::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(ListStudentSubgroups::class)
        ->assertCanSeeTableRecords([$ownGroup])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's group", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $group = StudentSubgroup::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $group))->toBeFalse()
        ->and($teacher->can('delete', $group))->toBeFalse();
});

it('splits a class into a balanced number of groups', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(10)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 3)
        ->call('generate');

    $groups = $component->get('generatedGroups');

    expect($groups)->toHaveCount(3);

    $sizes = array_map('count', $groups);
    expect(max($sizes) - min($sizes))->toBeLessThanOrEqual(1)
        ->and(array_sum($sizes))->toBe(10);

    $allIds = array_merge(...$groups);
    expect($allIds)->toHaveCount(10)
        ->and(array_unique($allIds))->toHaveCount(10);
});

it('splits a class by target group size', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(9)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'size')
        ->set('groupSize', 3)
        ->call('generate');

    $groups = $component->get('generatedGroups');

    expect($groups)->toHaveCount(3);

    foreach ($groups as $group) {
        expect(count($group))->toBe(3);
    }
});

it('excludes marked students from the generated groups', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $absent = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->call('toggleExcluded', $absent->id)
        ->call('generate');

    $allIds = array_merge(...$component->get('generatedGroups'));

    expect($allIds)->not->toContain($absent->id)
        ->and($allIds)->toHaveCount(3);
});

it('saves generated groups and replaces the previous ones', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(4)->create();
    $staleGroup = StudentSubgroup::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->set('mode', 'count')
        ->set('groupCount', 2)
        ->call('generate')
        ->call('save');

    expect(StudentSubgroup::query()->find($staleGroup->id))->toBeNull();

    $groups = StudentSubgroup::query()->where('school_class_id', $class->id)->get();

    expect($groups)->toHaveCount(2);

    $memberIds = $groups->flatMap(fn (StudentSubgroup $group) => $group->students()->pluck('students.id'));

    expect($memberIds->sort()->values()->all())->toBe($students->pluck('id')->sort()->values()->all());
});

it("keeps the group generator's student pool isolated from another teacher's class", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $students = Livewire::test(GroupGenerator::class)
        ->set('schoolClassId', $class->id)
        ->get('students');

    expect($students)->toHaveCount(1)
        ->and($students->first()->id)->toBe($student->id);
});
