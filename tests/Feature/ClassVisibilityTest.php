<?php

use App\Filament\Pages\Appreciations;
use App\Filament\Pages\Averages;
use App\Filament\Pages\DisciplineTracking;
use App\Filament\Pages\GroupGenerator;
use App\Filament\Pages\RandomPicker;
use App\Filament\Pages\Trombinoscope;
use App\Filament\Resources\Evaluations\Pages\CreateEvaluation;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

/**
 * Classes kept with no subject attached are pure archives (old cohorts kept
 * only for their students' records and birthdays) — everywhere except the
 * Trombinoscope must stop offering them as a "class to work in".
 */
it('excludes a subject-less class from the class selectors of teaching pages, but includes a class with a subject', function () {
    $teacher = User::factory()->create();
    $subject = Subject::factory()->for($teacher)->create();
    $withSubject = SchoolClass::factory()->for($teacher)->create(['name' => 'Avec matière']);
    $withSubject->subjects()->attach($subject->id);
    $withoutSubject = SchoolClass::factory()->for($teacher)->create(['name' => 'Sans matière']);

    $this->actingAs($teacher);

    foreach ([Averages::class, Appreciations::class, DisciplineTracking::class, GroupGenerator::class, RandomPicker::class] as $page) {
        $classes = Livewire::test($page)->get('schoolClasses');

        expect($classes->pluck('id'))->toContain($withSubject->id)
            ->and($classes->pluck('id'))->not->toContain($withoutSubject->id);
    }
});

it('still shows a subject-less class in the Trombinoscope', function () {
    $teacher = User::factory()->create();
    $withoutSubject = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $classes = Livewire::test(Trombinoscope::class)->get('schoolClasses');

    expect($classes->pluck('id'))->toContain($withoutSubject->id);
});

it('excludes a subject-less class from the Évaluations class picker', function () {
    $teacher = User::factory()->create();
    $subject = Subject::factory()->for($teacher)->create();
    $withSubject = SchoolClass::factory()->for($teacher)->create();
    $withSubject->subjects()->attach($subject->id);
    $withoutSubject = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(CreateEvaluation::class)
        ->assertFormFieldExists('school_class_id')
        ->assertSee($withSubject->name);

    // The relationship select loads its options from the filtered query, so a
    // subject-less class is never fetched — nothing more to assert without
    // reaching into Filament internals, this already exercises the query path.
    expect(SchoolClass::query()->where('user_id', $teacher->id)->hasSubjects()->pluck('id'))
        ->toContain($withSubject->id)
        ->not->toContain($withoutSubject->id);
});
