<?php

use App\Models\User;
use App\Services\EcoleDirecteIcsImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly database backup, cleanup of old backups per the retention policy in
// config/backup.php, then a health check that emails BACKUP_NOTIFICATION_EMAIL
// if a backup is missing, stale, or oversized. Requires the server crontab to
// run `php artisan schedule:run` every minute (set up in the CloudPanel phase).
Schedule::command('backup:run --only-db')->dailyAt('03:00')->onOneServer();
Schedule::command('backup:clean')->dailyAt('03:30')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('04:00')->onOneServer();

// Nightly refresh of every teacher's Ecole-Directe ICS occurrences, so the
// "associer à une séance de l'emploi du temps" picker stays current without
// requiring a manual "Rafraîchir" click. One teacher's fetch failure (bad
// URL, Ecole-Directe outage) must never block the others.
Schedule::call(function () {
    User::query()->whereNotNull('ecole_directe_ics_url')->each(
        fn (User $user) => rescue(fn () => app(EcoleDirecteIcsImporter::class)->importForUser($user))
    );
})->dailyAt('05:00')->name('ecole-directe-sync')->onOneServer();
