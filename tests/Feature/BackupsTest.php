<?php

use App\Filament\Pages\Backups;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('has a sensible backup configuration', function () {
    expect(config('backup.backup.name'))->toBe('CarnetProf')
        ->and(config('backup.backup.destination.disks'))->toBe(['local'])
        ->and(config('backup.notifications.mail.to'))->not->toBeEmpty();
});

it('registers the spatie backup artisan commands', function () {
    expect(Artisan::all())
        ->toHaveKeys(['backup:run', 'backup:clean', 'backup:monitor']);
});

it('schedules the nightly backup, cleanup and monitor commands', function () {
    $events = app(Schedule::class)->events();
    $commands = collect($events)->map(fn ($event) => $event->command)->filter();

    expect($commands->filter(fn ($command) => str_contains($command, 'backup:run')))->not->toBeEmpty()
        ->and($commands->filter(fn ($command) => str_contains($command, 'backup:clean')))->not->toBeEmpty()
        ->and($commands->filter(fn ($command) => str_contains($command, 'backup:monitor')))->not->toBeEmpty();
});

it('lists existing backups from the configured disk', function () {
    Storage::fake('local');
    Storage::disk('local')->put('CarnetProf/2026-01-01-10-00-00.zip', str_repeat('a', 2048));

    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    $backups = Livewire::test(Backups::class)->get('backups');

    expect($backups)->toHaveCount(1)
        ->and($backups->first()['disk'])->toBe('local')
        ->and($backups->first()['size'])->toBe('2.0 Ko');
});

it('runs a manual backup and reports the outcome', function () {
    Storage::fake('local');

    $teacher = User::factory()->create();
    $this->actingAs($teacher);

    Livewire::test(Backups::class)->call('runBackup');

    expect(Storage::disk('local')->allFiles())->not->toBeEmpty();
});
