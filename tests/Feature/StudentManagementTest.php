<?php

use App\Filament\Resources\Students\Pages\CreateStudent;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\RelationManagers\GuardiansRelationManager;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use App\Models\User;
use Livewire\Livewire;

it('only shows a teacher their own students', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $ownClass = SchoolClass::factory()->for($teacher)->create();
    $ownStudents = Student::factory()->count(2)->for($teacher)->for($ownClass, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->count(3)->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords($ownStudents)
        ->assertCountTableRecords(2);
});

it('lets a teacher create a student stamped with their id', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(CreateStudent::class)
        ->fillForm([
            'school_class_id' => $class->id,
            'first_name' => 'Camille',
            'last_name' => 'Dupont',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Student::query()->where('user_id', $teacher->id)->where('last_name', 'Dupont')->exists())->toBeTrue();
});

it('archives and unarchives a student from the table', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(ListStudents::class)->callTableAction('archive', $student);
    expect($student->refresh()->is_archived)->toBeTrue();

    Livewire::test(ListStudents::class)
        ->filterTable('is_archived', null)
        ->callTableAction('unarchive', $student);
    expect($student->refresh()->is_archived)->toBeFalse();
});

it('hides archived students from the default table view', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $active = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $archived = Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create();

    $this->actingAs($teacher);

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);
});

it("prevents a teacher from viewing, updating or deleting another teacher's student", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $student = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('view', $student))->toBeFalse()
        ->and($teacher->can('update', $student))->toBeFalse()
        ->and($teacher->can('delete', $student))->toBeFalse();
});

it('lets a teacher attach an existing guardian to a student as primary contact', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $guardian = Guardian::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(GuardiansRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->callTableAction('attach', data: [
            'recordId' => $guardian->id,
            'is_primary' => true,
        ]);

    expect($student->guardians()->where('guardian_id', $guardian->id)->exists())->toBeTrue();
    expect($student->guardians()->first()->pivot->is_primary)->toBeTrue();
});

it('only lets a subgroup contain students from its own class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $subgroup = StudentSubgroup::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $student->subgroups()->attach($subgroup);

    expect($student->subgroups()->first()->id)->toBe($subgroup->id)
        ->and($subgroup->schoolClass->id)->toBe($class->id);
});
