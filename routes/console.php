<?php

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
