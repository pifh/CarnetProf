<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ApiIntegration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Intégration API';

    protected static ?string $title = 'Intégration API (NAS / N8N)';

    protected static ?int $navigationSort = 88;

    protected string $view = 'filament.pages.api-integration';

    public function getBirthdaysUrlProperty(): string
    {
        return route('api.nas.birthdays', ['token' => Auth::user()->ensureApiToken()]);
    }

    public function getScheduleUrlProperty(): string
    {
        return route('api.nas.schedule', ['token' => Auth::user()->ensureApiToken()]);
    }

    public function getRemindersUrlProperty(): string
    {
        return route('api.nas.reminders', ['token' => Auth::user()->ensureApiToken()]);
    }

    public function getHomeworkUrlProperty(): string
    {
        return route('api.nas.homework', ['token' => Auth::user()->ensureApiToken()]);
    }

    public function regenerateToken(): void
    {
        Auth::user()->regenerateApiToken();

        Notification::make()->title('Jeton régénéré. Pensez à mettre à jour vos flux N8N.')->success()->send();
    }
}
