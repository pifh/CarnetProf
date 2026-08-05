<?php

use App\Filament\Pages\DisciplineTracking;
use App\Models\DisciplineEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

it('defaults to the first active class and the saved (or default) thresholds', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['name' => 'A - 6e A']);
    SchoolClass::factory()->for($teacher)->create(['name' => 'B - 5e B']);
    $this->actingAs($teacher);

    Livewire::test(DisciplineTracking::class)
        ->assertSet('schoolClassId', $class->id)
        ->assertSet('thresholds.oubli_materiel', 2);
});

it('logs one entry per click and reflects it in total and trip counts', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(DisciplineTracking::class)
        ->call('log', $student->id, 'oubli_materiel')
        ->call('log', $student->id, 'oubli_materiel');

    expect($component->instance()->countsFor($student->id, 'oubli_materiel'))->toBe(['total' => 2, 'trip' => 2])
        ->and(DisciplineEntry::query()->where('student_id', $student->id)->count())->toBe(2);
});

it("resets a student's counter for one category via the page action", function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(DisciplineTracking::class)
        ->call('log', $student->id, 'oubli_materiel')
        ->call('log', $student->id, 'oubli_materiel')
        ->call('resetStudent', $student->id, 'oubli_materiel');

    expect($component->instance()->countsFor($student->id, 'oubli_materiel'))->toBe(['total' => 2, 'trip' => 0]);
});

it('resets a category for the whole class via the page action', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $component = Livewire::test(DisciplineTracking::class)
        ->call('log', $studentA->id, 'bavardage')
        ->call('log', $studentB->id, 'bavardage')
        ->call('resetClass', 'bavardage');

    expect($component->instance()->countsFor($studentA->id, 'bavardage')['trip'])->toBe(0)
        ->and($component->instance()->countsFor($studentB->id, 'bavardage')['trip'])->toBe(0);
});

it('saves custom thresholds to the teacher account', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(DisciplineTracking::class)
        ->set('thresholds.oubli_materiel', 5)
        ->call('saveThresholds')
        ->assertHasNoErrors();

    expect($teacher->fresh()->discipline_thresholds['oubli_materiel'])->toBe(5);
});

it('rejects a non-numeric threshold', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(DisciplineTracking::class)
        ->set('thresholds.oubli_materiel', 'abc')
        ->call('saveThresholds')
        ->assertHasErrors(['thresholds.oubli_materiel']);
});

it("lists all dates for a student's history, grouped by category", function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    DisciplineEntry::factory()->for($teacher)->for($student)->create(['category' => 'oubli_materiel', 'occurred_at' => '2026-09-10']);
    DisciplineEntry::factory()->for($teacher)->for($student)->create(['category' => 'oubli_materiel', 'occurred_at' => '2026-09-17']);
    DisciplineEntry::factory()->for($teacher)->for($student)->create(['category' => 'bavardage', 'occurred_at' => '2026-09-12']);

    $component = Livewire::test(DisciplineTracking::class)->call('showHistory', $student->id);

    $byCategory = $component->instance()->historyByCategory;

    expect($byCategory->get('oubli_materiel'))->toHaveCount(2)
        ->and($byCategory->get('bavardage'))->toHaveCount(1)
        ->and($byCategory->has('discipline'))->toBeFalse();
});

it('deletes a single entry, correcting a mis-click without touching the others', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $toDelete = DisciplineEntry::factory()->for($teacher)->for($student)->create(['category' => 'oubli_materiel']);
    DisciplineEntry::factory()->for($teacher)->for($student)->create(['category' => 'oubli_materiel']);

    $component = Livewire::test(DisciplineTracking::class)->call('deleteEntry', $toDelete->id);

    expect(DisciplineEntry::query()->find($toDelete->id))->toBeNull()
        ->and($component->instance()->countsFor($student->id, 'oubli_materiel'))->toBe(['total' => 1, 'trip' => 1]);
});

it("refuses to delete another teacher's entry", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $otherEntry = DisciplineEntry::factory()->for($otherTeacher)->for($otherStudent)->create();

    $this->actingAs($teacher);

    expect(fn () => Livewire::test(DisciplineTracking::class)->call('deleteEntry', $otherEntry->id))
        ->toThrow(ModelNotFoundException::class);

    expect(DisciplineEntry::query()->withoutGlobalScopes()->find($otherEntry->id))->not->toBeNull();
});

it("only shows and mutates the acting teacher's own students", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    expect(fn () => Livewire::test(DisciplineTracking::class)->call('log', $otherStudent->id, 'oubli_materiel'))
        ->toThrow(ModelNotFoundException::class);

    expect(DisciplineEntry::query()->withoutGlobalScopes()->where('student_id', $otherStudent->id)->count())->toBe(0);
});
