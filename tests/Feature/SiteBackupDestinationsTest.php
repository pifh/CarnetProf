<?php

use App\Filament\Pages\SiteBackupDestinations;
use App\Models\BackupDestination;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Livewire\Livewire;

it('denies a plain teacher access to the site destinations page', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(SiteBackupDestinations::class)->assertForbidden();
});

it('allows an admin to manage site-wide destinations', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(SiteBackupDestinations::class)
        ->set('label', 'Serveur site')
        ->set('provider', 'sftp')
        ->set('credentials', ['host' => 'sftp.example.com', 'username' => 'u', 'password' => 'p'])
        ->call('save')
        ->assertHasNoErrors();

    $destination = BackupDestination::first();
    expect($destination->user_id)->toBeNull()
        ->and($destination->provider)->toBe('sftp');
});

it('injects active site-wide destinations into the runtime backup config', function () {
    $destination = BackupDestination::factory()->siteWide()->create([
        'provider' => 'sftp',
        'credentials' => ['host' => 'sftp.example.com', 'username' => 'u', 'password' => 'p'],
        'is_active' => true,
    ]);

    app()->getProvider(AppServiceProvider::class)->registerSiteBackupDestinations();

    $diskName = $destination->diskName();

    expect(config('backup.backup.destination.disks'))->toContain($diskName)
        ->and(config("filesystems.disks.{$diskName}.driver"))->toBe('sftp');
});

it('does not inject an inactive site-wide destination', function () {
    $destination = BackupDestination::factory()->siteWide()->create(['is_active' => false]);

    app()->getProvider(AppServiceProvider::class)->registerSiteBackupDestinations();

    expect(config('backup.backup.destination.disks'))->not->toContain($destination->diskName());
});
