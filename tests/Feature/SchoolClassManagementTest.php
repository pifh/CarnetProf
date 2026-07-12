<?php

use App\Filament\Resources\SchoolClasses\Pages\CreateSchoolClass;
use App\Filament\Resources\SchoolClasses\Pages\ListSchoolClasses;
use App\Models\SchoolClass;
use App\Models\User;
use Livewire\Livewire;

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

it('hides archived classes from the default table view', function () {
    $teacher = User::factory()->create();
    $active = SchoolClass::factory()->for($teacher)->create();
    $archived = SchoolClass::factory()->for($teacher)->archived()->create();

    $this->actingAs($teacher);

    Livewire::test(ListSchoolClasses::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$archived]);
});

it("prevents a teacher from updating or deleting another teacher's class", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $class))->toBeFalse()
        ->and($teacher->can('delete', $class))->toBeFalse();
});
