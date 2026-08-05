<?php

namespace App\Providers;

use App\Filament\Navigation\UserNavigationManager;
use App\Models\BackupDestination;
use App\Services\BackupDestinationDiskFactory;
use Filament\Navigation\NavigationManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Sabre\DAV\Client;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerWebDavDriver();
        $this->registerSiteBackupDestinations();
        $this->registerUserNavigationManager();
    }

    /**
     * Overrides Filament's own binding (registered in its ServiceProvider's
     * register(), which may run before or after this one) so every
     * teacher's sidebar_order/sidebar_hidden preferences apply on top of
     * the panel's auto-discovered navigation. Must happen in boot(), not
     * register(), to reliably win regardless of provider load order.
     */
    private function registerUserNavigationManager(): void
    {
        $this->app->scoped(NavigationManager::class, fn () => new UserNavigationManager);
    }

    /**
     * Spatie's backup:run/backup:clean read config('backup.backup.destination.disks')
     * (the package nests its own 'destination' key under a top-level
     * 'backup' key inside config/backup.php) and resolve each name via
     * Storage::disk($name). Site-wide
     * BackupDestination rows (user_id null) live in the database, so their
     * disk config is injected here at boot — every process (the nightly
     * cron-driven schedule:run, and the in-request manual "Sauvegarder
     * maintenant" button) re-runs this on its own fresh bootstrap, so both
     * always see current DB state without any special-casing.
     *
     * Public (not just called from boot()) so tests can re-invoke it after
     * creating a BackupDestination within the same process, where boot()
     * itself only runs once at the very start of the test suite.
     */
    public function registerSiteBackupDestinations(): void
    {
        if (! Schema::hasTable('backup_destinations')) {
            return;
        }

        $names = [];

        BackupDestination::query()->whereNull('user_id')->where('is_active', true)->get()
            ->each(function (BackupDestination $destination) use (&$names) {
                $name = $destination->diskName();
                Config::set("filesystems.disks.{$name}", app(BackupDestinationDiskFactory::class)->configFor($destination));
                $names[] = $name;
            });

        if ($names !== []) {
            Config::set('backup.backup.destination.disks', array_values(array_unique([
                ...config('backup.backup.destination.disks'),
                ...$names,
            ])));
        }
    }

    /**
     * Laravel has no built-in WebDAV disk driver (unlike ftp/sftp/s3), so it
     * must be registered manually. Runs unconditionally — this is just a
     * closure registration, cheap even when no WebDAV destination exists.
     */
    private function registerWebDavDriver(): void
    {
        Storage::extend('webdav', function ($app, array $config) {
            $client = new Client([
                'baseUri' => $config['baseUri'],
                'userName' => $config['userName'] ?? null,
                'password' => $config['password'] ?? null,
            ]);

            $adapter = new WebDAVAdapter($client, $config['root'] ?? '');

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }
}
