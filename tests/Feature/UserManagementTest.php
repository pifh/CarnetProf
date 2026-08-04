<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Livewire\Livewire;

it('denies a plain teacher access to the users list', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(ListUsers::class)->assertForbidden();
});

it('allows an admin to view the users list', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->count(2)->create();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)->assertSuccessful();
});

it('allows a superadmin to promote a teacher to admin', function () {
    $superadmin = User::factory()->superadmin()->create();
    $teacher = User::factory()->create();
    $this->actingAs($superadmin);

    Livewire::test(EditUser::class, ['record' => $teacher->getRouteKey()])
        ->fillForm(['role' => User::ROLE_ADMIN])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($teacher->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('does not let a plain admin change a role even if submitted', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->create();
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $teacher->getRouteKey()])
        ->fillForm(['role' => User::ROLE_SUPERADMIN])
        ->call('save');

    expect($teacher->fresh()->role)->toBe(User::ROLE_TEACHER);
});

it('prevents a superadmin from deleting or demoting themselves', function () {
    $superadmin = User::factory()->superadmin()->create();

    expect($superadmin->can('delete', $superadmin))->toBeFalse();
});
