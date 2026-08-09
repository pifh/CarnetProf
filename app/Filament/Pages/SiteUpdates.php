<?php

namespace App\Filament\Pages;

use App\Services\SiteUpdater;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SiteUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Mises à jour';

    protected static ?string $title = 'Mises à jour';

    protected static ?int $navigationSort = 92;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected string $view = 'filament.pages.site-updates';

    public ?string $currentHash = null;

    public ?string $currentSummary = null;

    public ?string $currentDate = null;

    public bool $hasChecked = false;

    public ?string $checkError = null;

    public int $behindBy = 0;

    /** @var array<int, string> */
    public array $pendingCommits = [];

    public ?bool $lastUpdateSuccessful = null;

    public ?string $lastUpdateOutput = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuperadmin() ?? false;
    }

    public function mount(): void
    {
        $this->refreshCurrentCommit();
    }

    public function checkForUpdates(): void
    {
        $result = app(SiteUpdater::class)->checkForUpdates();

        $this->hasChecked = true;
        $this->checkError = $result['error'];
        $this->behindBy = $result['behind_by'];
        $this->pendingCommits = $result['commits'];

        if ($result['error']) {
            Notification::make()->title('Vérification impossible.')->body($result['error'])->danger()->send();
        } elseif ($result['behind_by'] === 0) {
            Notification::make()->title('Le site est déjà à jour.')->success()->send();
        } else {
            Notification::make()->title($result['behind_by'].' commit(s) en attente.')->send();
        }
    }

    public function runUpdate(): void
    {
        set_time_limit(0);

        $result = app(SiteUpdater::class)->runUpdate();

        $this->lastUpdateSuccessful = $result['successful'];
        $this->lastUpdateOutput = $result['output'];

        $this->refreshCurrentCommit();
        $this->hasChecked = false;
        $this->behindBy = 0;
        $this->pendingCommits = [];

        if ($result['successful']) {
            Notification::make()->title('Mise à jour effectuée avec succès.')->success()->send();
        } else {
            Notification::make()
                ->title('La mise à jour a échoué.')
                ->body('Le site est peut-être resté en mode maintenance — consultez le journal ci-dessous.')
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private function refreshCurrentCommit(): void
    {
        $commit = app(SiteUpdater::class)->currentCommit();

        $this->currentHash = $commit['hash'];
        $this->currentSummary = $commit['summary'];
        $this->currentDate = $commit['date'];
    }
}
