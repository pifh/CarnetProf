<?php

use App\Filament\Resources\Terms\Pages\CreateTerm;
use App\Filament\Resources\Terms\Pages\EditTerm;
use App\Filament\Resources\Terms\Pages\ListTerms;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

it('only shows a teacher their own terms', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $ownTerms = Term::factory()->count(2)->for($teacher)->create();
    Term::factory()->count(3)->for($otherTeacher)->create();

    $this->actingAs($teacher);

    Livewire::test(ListTerms::class)
        ->assertCanSeeTableRecords($ownTerms)
        ->assertCountTableRecords(2);
});

it('lets a teacher create a trimestre with dates', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'label' => 'Trimestre 1',
            'school_year' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-19',
            'position' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $term = Term::query()->where('user_id', $teacher->id)->where('label', 'Trimestre 1')->first();

    expect($term)->not->toBeNull()
        ->and($term->starts_on->toDateString())->toBe('2026-09-01')
        ->and($term->ends_on->toDateString())->toBe('2026-12-19')
        ->and($term->parent_id)->toBeNull();
});

it('lets a teacher create a période nested inside a trimestre', function () {
    $teacher = User::factory()->create();
    $trimestre = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1']);
    $this->actingAs($teacher);

    Livewire::test(CreateTerm::class)
        ->fillForm([
            'label' => 'Période 1',
            'school_year' => '2026-2027',
            'parent_id' => $trimestre->id,
            'position' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $periode = Term::query()->where('user_id', $teacher->id)->where('label', 'Période 1')->first();

    expect($periode->parent_id)->toBe($trimestre->id);
});

it('only offers root-level terms as possible parents, excluding the record itself', function () {
    $teacher = User::factory()->create();
    $trimestreUn = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1']);
    $trimestreDeux = Term::factory()->for($teacher)->create(['label' => 'Trimestre 2']);
    $periode = Term::factory()->for($teacher)->create(['label' => 'Période 1', 'parent_id' => $trimestreUn->id]);
    $this->actingAs($teacher);

    Livewire::test(EditTerm::class, ['record' => $trimestreUn->getRouteKey()])
        ->assertFormFieldExists('parent_id')
        ->assertFormSet(fn () => ['parent_id' => null]);

    $editingTrimestreUn = Livewire::test(EditTerm::class, ['record' => $trimestreUn->getRouteKey()]);
    $parentOptions = $editingTrimestreUn->instance()->form->getComponent('parent_id')->getOptions();

    expect($parentOptions)->toHaveKey($trimestreDeux->id)
        ->and($parentOptions)->not->toHaveKey($trimestreUn->id)
        ->and($parentOptions)->not->toHaveKey($periode->id);
});

it('deleting a trimestre cascades to its périodes', function () {
    $teacher = User::factory()->create();
    $trimestre = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1']);
    $periode = Term::factory()->for($teacher)->create(['label' => 'Période 1', 'parent_id' => $trimestre->id]);

    $trimestre->delete();

    expect(Term::query()->find($periode->id))->toBeNull();
});

it("prevents a teacher from updating or deleting another teacher's term", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $term = Term::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $term))->toBeFalse()
        ->and($teacher->can('delete', $term))->toBeFalse();
});
