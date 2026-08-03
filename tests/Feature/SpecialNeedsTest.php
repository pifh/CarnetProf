<?php

use App\Filament\Pages\SpecialNeeds;
use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

it('saves special needs as an array of tags from the student form', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(EditStudent::class, ['record' => $student->getKey()])
        ->fillForm(['special_needs' => ['Arial 16', 'calculatrice']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($student->refresh()->special_needs)->toBe(['Arial 16', 'calculatrice']);
});

it('lists distinct special needs tags across a teacher\'s own students only', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();

    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['special_needs' => ['Arial 16', 'orthographe']]);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['special_needs' => ['orthographe', 'calculatrice']]);
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create(['special_needs' => ['dyslexie']]);

    $this->actingAs($teacher);

    expect(Student::allSpecialNeedsTags())->toBe(['Arial 16', 'calculatrice', 'orthographe']);
});

it('only shows students with special needs for the selected class on the Besoins particuliers page', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($teacher)->create();

    $withNeeds = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['special_needs' => ['Arial 16']]);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['special_needs' => null]);
    Student::factory()->for($teacher)->for($otherClass, 'schoolClass')->create(['special_needs' => ['calculatrice']]);

    $this->actingAs($teacher);

    $component = Livewire::test(SpecialNeeds::class)->set('schoolClassId', $class->id);

    expect($component->get('students')->pluck('id')->all())->toBe([$withNeeds->id]);

    $component->assertSee('Arial 16');
});

it('isolates the Besoins particuliers page between teachers', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create(['special_needs' => ['dyslexie']]);

    $this->actingAs($teacher);

    Livewire::test(SpecialNeeds::class)
        ->assertSet('schoolClassId', null)
        ->assertDontSee('dyslexie');
});
