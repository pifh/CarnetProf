<?php

use App\Filament\Pages\CalendrierReglages;
use App\Models\PersonalBirthday;
use App\Models\User;
use Livewire\Livewire;

it('lets a teacher create a personal birthday', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(CalendrierReglages::class)
        ->set('birthdayName', 'Marie Dupont')
        ->set('birthdayDate', '1985-03-14')
        ->call('saveBirthday')
        ->assertHasNoErrors();

    $birthday = PersonalBirthday::first();
    expect($birthday->user_id)->toBe($teacher->id)
        ->and($birthday->name)->toBe('Marie Dupont');
});

it('lets a teacher edit and delete their own personal birthday', function () {
    $teacher = User::factory()->create();
    $birthday = PersonalBirthday::factory()->for($teacher)->create(['name' => 'Ancien nom']);
    $this->actingAs($teacher);

    Livewire::test(CalendrierReglages::class)
        ->call('editBirthday', $birthday->id)
        ->set('birthdayName', 'Nouveau nom')
        ->call('saveBirthday');

    expect($birthday->fresh()->name)->toBe('Nouveau nom');

    Livewire::test(CalendrierReglages::class)->call('deleteBirthday', $birthday->id);

    expect(PersonalBirthday::find($birthday->id))->toBeNull();
});

it("prevents a teacher from updating or deleting another teacher's personal birthday", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $birthday = PersonalBirthday::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $birthday))->toBeFalse()
        ->and($teacher->can('delete', $birthday))->toBeFalse();
});

it("only lists a teacher's own personal birthdays", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $own = PersonalBirthday::factory()->for($teacher)->create();
    PersonalBirthday::factory()->for($otherTeacher)->create();

    $this->actingAs($teacher);

    $birthdays = Livewire::test(CalendrierReglages::class)->get('personalBirthdays');

    expect($birthdays)->toHaveCount(1)
        ->and($birthdays->first()->id)->toBe($own->id);
});
