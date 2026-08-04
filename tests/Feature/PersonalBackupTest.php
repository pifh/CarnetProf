<?php

use App\Filament\Pages\MyBackup;
use App\Models\BackupDestination;
use App\Models\User;
use App\Services\BackupDestinationDiskFactory;
use App\Services\PersonalDataExporter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('lets a teacher create a personal destination for each provider', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(MyBackup::class)
        ->set('label', 'Mon FTP')
        ->set('provider', 'ftp')
        ->set('credentials', ['host' => 'ftp.example.com', 'username' => 'u', 'password' => 'p'])
        ->call('save')
        ->assertHasNoErrors();

    $destination = BackupDestination::first();
    expect($destination->user_id)->toBe($teacher->id)
        ->and($destination->provider)->toBe('ftp');
});

it('encrypts credentials at rest', function () {
    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(MyBackup::class)
        ->set('label', 'Mon FTP')
        ->set('provider', 'ftp')
        ->set('credentials', ['host' => 'ftp.example.com', 'username' => 'u', 'password' => 'secret-password'])
        ->call('save');

    $raw = DB::table('backup_destinations')->value('credentials');
    expect($raw)->not->toContain('secret-password');

    $destination = BackupDestination::first();
    expect($destination->credentials['password'])->toBe('secret-password');
});

it("only shows a teacher their own destinations, never another teacher's or site-wide ones", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    BackupDestination::factory()->for($teacher)->create(['label' => 'Mine']);
    BackupDestination::factory()->for($otherTeacher)->create(['label' => 'Not mine']);
    BackupDestination::factory()->siteWide()->create(['label' => 'Site-wide']);

    $this->actingAs($teacher);

    $destinations = Livewire::test(MyBackup::class)->get('destinations');

    expect($destinations)->toHaveCount(1)
        ->and($destinations->first()->label)->toBe('Mine');
});

it('pushes the export to each active destination via the disk factory', function () {
    Storage::fake('public');
    $fakeDisk = Storage::fake('personal-backup-destination');

    $teacher = User::factory()->create();
    $destination = BackupDestination::factory()->for($teacher)->create(['is_active' => true]);

    $this->actingAs($teacher);

    $this->mock(BackupDestinationDiskFactory::class, function ($mock) use ($fakeDisk) {
        $mock->shouldReceive('make')->andReturn($fakeDisk);
    });

    app(PersonalDataExporter::class)->export();

    expect($fakeDisk->allFiles())->not->toBeEmpty()
        ->and($destination->fresh()->last_used_at)->not->toBeNull();
});
