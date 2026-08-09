<?php

use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

it('the hasSubjects scope includes only classes with at least one subject attached', function () {
    $teacher = User::factory()->create();
    $withSubject = SchoolClass::factory()->for($teacher)->create();
    $subject = Subject::factory()->for($teacher)->create();
    $withSubject->subjects()->attach($subject->id);
    $withoutSubject = SchoolClass::factory()->for($teacher)->create();

    $classes = SchoolClass::query()->where('user_id', $teacher->id)->hasSubjects()->get();

    expect($classes->pluck('id'))->toContain($withSubject->id)
        ->and($classes->pluck('id'))->not->toContain($withoutSubject->id);
});

it('only shows a teacher their own classes', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $ownClasses = SchoolClass::factory()->count(2)->for($teacher)->create();
    SchoolClass::factory()->count(3)->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ListSchoolClasses::class)
        ->assertCanSeeTableRecords($ownClasses)
        ->assertCountTableRecords(2);
});

it('lets a teacher create a class that is automatically stamped with their id', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CreateSchoolClass::class)
        ->fillForm([
            'name' => '6e A',
            'school_year' => '2026-2027',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SchoolClass::query()->where('user_id', $teacher->id)->where('name', '6e A')->exists())->toBeTrue();
});

it('archives and unarchives a class from the table', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ListSchoolClasses::class)->callTableAction('archive', $class);
    expect($class->refresh()->is_archived)->toBeTrue();

    Livewire::test(ListSchoolClasses::class)
        ->filterTable('is_archived', null)
        ->callTableAction('unarchive', $class);
    expect($class->refresh()->is_archived)->toBeFalse();
});

it('archiving a class also archives its active students, but not the reverse', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $activeStudent = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $alreadyArchivedStudent = Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create();

    $this->actingAs($teacher);

    Livewire::test(ListSchoolClasses::class)->callTableAction('archive', $class);

    expect($activeStudent->refresh()->is_archived)->toBeTrue()
        ->and($alreadyArchivedStudent->refresh()->is_archived)->toBeTrue();

    Livewire::test(ListSchoolClasses::class)
        ->filterTable('is_archived', null)
        ->callTableAction('unarchive', $class);

    expect($class->refresh()->is_archived)->toBeFalse()
        ->and($activeStudent->refresh()->is_archived)->toBeTrue();
});

it('soft-deletes, restores and force-deletes a class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $class->delete();
    expect(SchoolClass::query()->find($class->id))->toBeNull()
        ->and(SchoolClass::withTrashed()->find($class->id))->not->toBeNull();

    $class->restore();
    expect(SchoolClass::query()->find($class->id))->not->toBeNull();

    $class->delete();
    $class->forceDelete();
    expect(SchoolClass::withTrashed()->find($class->id))->toBeNull();
});

it('exposes trash actions on the classes table for a soft-deleted class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $class->delete();

    $this->actingAs($teacher);

    Livewire::test(ListSchoolClasses::class)
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$class]);
});

it('hides archived classes from the default table view', function () {
    $teacher = User::factory()->create();
    $active = SchoolClass::factory()->for($teacher)->create();
    $archived = SchoolClass::factory()->for($teacher)->archived()->create();

    $this->actingAs($teacher);

    Livewire::test(ListSchoolClasses::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('lets a class be linked to several subjects while keeping a single shared roster', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(EditSchoolClass::class, ['record' => $class->getRouteKey()])
        ->fillForm(['subjects' => [$maths->id, $informatique->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($class->refresh()->subjects->pluck('id')->sort()->values()->all())
        ->toBe(collect([$maths->id, $informatique->id])->sort()->values()->all())
        ->and($class->students()->pluck('id')->all())->toBe([$student->id]);
});

it("prevents a teacher from updating or deleting another teacher's class", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $class))->toBeFalse()
        ->and($teacher->can('delete', $class))->toBeFalse();
});
