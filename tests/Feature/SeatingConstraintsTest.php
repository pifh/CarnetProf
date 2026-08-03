<?php

use App\Filament\Resources\Students\Pages\EditStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('saves the row preference and allowed columns on a student', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(EditStudent::class, ['record' => $student->getKey()])
        ->fillForm([
            'seating_row_preference' => 'closest',
            'seating_allowed_columns' => ['1', '2'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $student->refresh();

    expect($student->seating_row_preference)->toBe('closest')
        ->and($student->seating_allowed_columns)->toBe(['1', '2']);
});

it('saves next-to, not-next-to and far-from pairs independently on the same pivot table', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $a = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $b = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $c = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(EditStudent::class, ['record' => $a->getKey()])
        ->fillForm([
            'seatingNextTo' => [$b->id],
            'seatingNotNextTo' => [$c->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($a->seatingNextTo()->pluck('students.id')->all())->toBe([$b->id])
        ->and($a->seatingNotNextTo()->pluck('students.id')->all())->toBe([$c->id])
        ->and($a->seatingFarFrom()->pluck('students.id')->all())->toBe([]);
});

it('re-syncing one pair type does not affect the other pair types for the same student', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $a = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $b = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $c = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $a->seatingNextTo()->sync([$b->id]);
    $a->seatingFarFrom()->sync([$c->id]);

    $a->seatingNextTo()->sync([$c->id]);

    expect($a->fresh()->seatingNextTo()->pluck('students.id')->all())->toBe([$c->id])
        ->and($a->fresh()->seatingFarFrom()->pluck('students.id')->all())->toBe([$c->id]);
});

it('cascades and removes seating pair rows when a student is force-deleted', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $a = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $b = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $a->seatingNextTo()->sync([$b->id]);

    expect(DB::table('student_seating_pairs')->count())->toBe(1);

    $a->forceDelete();

    expect(DB::table('student_seating_pairs')->count())->toBe(0);
});
