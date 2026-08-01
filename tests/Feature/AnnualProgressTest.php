<?php

use App\Filament\Pages\AnnualProgress;
use App\Filament\Resources\ProgressionSequences\Pages\CreateProgressionSequence;
use App\Filament\Resources\ProgressionSequences\Pages\ListProgressionSequences;
use App\Models\ProgressionSequence;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it("only lists a teacher's own progression sequences", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $class = SchoolClass::factory()->for($teacher)->create();
    $ownSequence = ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    ProgressionSequence::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    Livewire::test(ListProgressionSequences::class)
        ->assertCanSeeTableRecords([$ownSequence])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's sequence", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $sequence = ProgressionSequence::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $sequence))->toBeFalse()
        ->and($teacher->can('delete', $sequence))->toBeFalse();
});

it('appends a new sequence to the end of its class list', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->create(['position' => 3]);

    $this->actingAs($teacher);

    Livewire::test(CreateProgressionSequence::class)
        ->fillForm([
            'school_class_id' => $class->id,
            'title' => 'Chapitre 4 : Les décimaux',
            'status' => 'not_started',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $newSequence = ProgressionSequence::query()->where('title', 'Chapitre 4 : Les décimaux')->first();

    expect($newSequence->position)->toBe(4);
});

it('orders sequences by position and computes the completion percentage', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->done()->create(['title' => 'A', 'position' => 1]);
    ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->create(['title' => 'B', 'position' => 2]);
    ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->inProgress()->create(['title' => 'C', 'position' => 0]);

    $this->actingAs($teacher);

    $component = Livewire::test(AnnualProgress::class)->set('schoolClassId', $class->id);

    expect($component->get('sequences')->pluck('title')->all())->toBe(['C', 'A', 'B'])
        ->and($component->get('progressPercent'))->toBe(33);
});

it('cycles a sequence through not_started, in_progress, done and back', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $sequence = ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(AnnualProgress::class)->set('schoolClassId', $class->id);

    $component->call('toggleStatus', $sequence->id);
    expect($sequence->fresh()->status)->toBe('in_progress')
        ->and($sequence->fresh()->completed_at)->toBeNull();

    $component->call('toggleStatus', $sequence->id);
    expect($sequence->fresh()->status)->toBe('done')
        ->and($sequence->fresh()->completed_at)->not->toBeNull();

    $component->call('toggleStatus', $sequence->id);
    expect($sequence->fresh()->status)->toBe('not_started')
        ->and($sequence->fresh()->completed_at)->toBeNull();
});

it('reports being ahead of pace when everything is done early in the school year', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 1));

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['school_year' => '2026-2027']);
    ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->done()->count(3)->create();

    $this->actingAs($teacher);

    $pacing = Livewire::test(AnnualProgress::class)
        ->set('schoolClassId', $class->id)
        ->get('pacing');

    expect($pacing['label'])->toBe('En avance');

    Carbon::setTestNow();
});

it('reports being behind pace when nothing is done late in the school year', function () {
    Carbon::setTestNow(Carbon::create(2027, 5, 1));

    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create(['school_year' => '2026-2027']);
    ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $pacing = Livewire::test(AnnualProgress::class)
        ->set('schoolClassId', $class->id)
        ->get('pacing');

    expect($pacing['label'])->toBe('En retard');

    Carbon::setTestNow();
});

it("keeps the annual progress page's sequences isolated from another teacher's class", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $sequence = ProgressionSequence::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    ProgressionSequence::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $sequences = Livewire::test(AnnualProgress::class)
        ->set('schoolClassId', $class->id)
        ->get('sequences');

    expect($sequences)->toHaveCount(1)
        ->and($sequences->first()->id)->toBe($sequence->id);
});
