<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Config\Config;

class Backups extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = 'Sauvegardes';

    protected static ?string $title = 'Sauvegardes';

    protected static ?int $navigationSort = 86;

    protected string $view = 'filament.pages.backups';

    /**
     * @return Collection<int, array{disk: string, date: Carbon, size: string}>
     */
    public function getBackupsProperty(): Collection
    {
        $destinations = BackupDestinationFactory::createFromArray(app(Config::class));

        return $destinations
            ->flatMap(fn ($destination) => $destination->backups()->map(fn ($backup) => [
                'disk' => $destination->diskName(),
                'date' => $backup->date(),
                'size' => $this->formatBytes($backup->sizeInBytes()),
            ]))
            ->sortByDesc('date')
            ->values();
    }

    public function runBackup(): void
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

        if ($exitCode === 0) {
            Notification::make()
                ->title('Sauvegarde effectuée avec succès.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('La sauvegarde a échoué. Consultez les journaux du serveur.')
                ->danger()
                ->send();
        }
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' o';
        }

        $units = ['Ko', 'Mo', 'Go'];
        $value = $bytes;

        foreach ($units as $unit) {
            $value /= 1024;

            if ($value < 1024) {
                return number_format($value, 1).' '.$unit;
            }
        }

        return number_format($value, 1).' Go';
    }
}
