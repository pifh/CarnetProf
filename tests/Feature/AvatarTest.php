<?php

use App\Filament\AvatarProviders\InitialsAvatarProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('has no avatar url until one is uploaded', function () {
    $user = User::factory()->create(['avatar' => null]);

    expect($user->getFilamentAvatarUrl())->toBeNull();
});

it('builds a public storage url once an avatar path is set', function () {
    $user = User::factory()->create(['avatar' => 'avatars/photo.jpg']);

    expect($user->getFilamentAvatarUrl())->toBe(Storage::disk('public')->url('avatars/photo.jpg'));
});

it('generates a local, network-free initials avatar', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $avatar = app(InitialsAvatarProvider::class)->get($user);

    expect($avatar)->toStartWith('data:image/svg+xml;base64,');

    $svg = base64_decode(str($avatar)->after('data:image/svg+xml;base64,'));
    expect($svg)->toContain('AL')
        ->and($svg)->toContain('<svg');
});

it('lets a teacher upload a profile photo from the profile page', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $file = UploadedFile::fake()->image('avatar.jpg');

    Livewire::test(EditProfile::class)
        ->set('data.avatar', $file)
        ->set('data.name', $user->name)
        ->set('data.email', $user->email)
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
});
