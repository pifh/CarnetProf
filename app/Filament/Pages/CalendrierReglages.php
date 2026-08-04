<?php

namespace App\Filament\Pages;

use App\Models\PersonalBirthday;
use App\Services\EcoleDirecteIcsImporter;
use App\Support\CalendarCategories;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

class CalendrierReglages extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Réglages du calendrier';

    protected string $view = 'filament.pages.calendrier-reglages';

    public ?string $ecoleDirecteIcsUrl = null;

    /** @var array<int, string> */
    public array $categories = [];

    public bool $showBirthdayForm = false;

    public ?int $editingBirthdayId = null;

    public string $birthdayName = '';

    public ?string $birthdayDate = null;

    public ?string $birthdayNotes = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->ecoleDirecteIcsUrl = $user->ecole_directe_ics_url;
        $this->categories = $user->calendarFeedCategoriesOrDefault();
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryLabelsProperty(): array
    {
        return CalendarCategories::labels();
    }

    public function getFeedUrlProperty(): string
    {
        return route('calendar.feed', ['token' => Auth::user()->ensureCalendarToken()]);
    }

    public function getWebcalUrlProperty(): string
    {
        return preg_replace('/^https?:\/\//', 'webcal://', $this->feedUrl);
    }

    public function regenerateToken(): void
    {
        Auth::user()->regenerateCalendarToken();

        Notification::make()->title('Lien régénéré. Pensez à mettre à jour votre abonnement.')->success()->send();
    }

    public function saveEcoleDirecteUrl(): void
    {
        $this->validate(['ecoleDirecteIcsUrl' => ['nullable', 'url', 'max:2000']]);

        Auth::user()->update(['ecole_directe_ics_url' => $this->ecoleDirecteIcsUrl]);

        Notification::make()->title('Adresse enregistrée.')->success()->send();
    }

    public function refreshEcoleDirecte(): void
    {
        try {
            $count = app(EcoleDirecteIcsImporter::class)->importForUser(Auth::user());

            Notification::make()->title($count.' séance(s) importée(s) depuis École-Directe.')->success()->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()->title('Échec de la récupération du flux École-Directe.')->danger()->send();
        }
    }

    public function saveCategories(): void
    {
        Auth::user()->update(['calendar_feed_categories' => $this->categories]);

        Notification::make()->title('Catégories mises à jour.')->success()->send();
    }

    /**
     * @return Collection<int, PersonalBirthday>
     */
    public function getPersonalBirthdaysProperty(): Collection
    {
        return PersonalBirthday::query()->where('user_id', Auth::id())->orderBy('name')->get();
    }

    public function addBirthday(): void
    {
        $this->resetBirthdayForm();
        $this->showBirthdayForm = true;
    }

    public function editBirthday(int $birthdayId): void
    {
        $birthday = PersonalBirthday::query()->where('user_id', Auth::id())->findOrFail($birthdayId);

        Gate::authorize('update', $birthday);

        $this->editingBirthdayId = $birthday->id;
        $this->birthdayName = $birthday->name;
        $this->birthdayDate = $birthday->date->format('Y-m-d');
        $this->birthdayNotes = $birthday->notes;
        $this->showBirthdayForm = true;
    }

    public function cancelBirthdayForm(): void
    {
        $this->resetBirthdayForm();
        $this->showBirthdayForm = false;
    }

    public function saveBirthday(): void
    {
        $this->validate([
            'birthdayName' => ['required', 'string', 'max:255'],
            'birthdayDate' => ['required', 'date'],
            'birthdayNotes' => ['nullable', 'string'],
        ]);

        $attributes = [
            'name' => $this->birthdayName,
            'date' => $this->birthdayDate,
            'notes' => $this->birthdayNotes,
        ];

        if ($this->editingBirthdayId) {
            $birthday = PersonalBirthday::query()->where('user_id', Auth::id())->findOrFail($this->editingBirthdayId);
            Gate::authorize('update', $birthday);
            $birthday->update($attributes);
        } else {
            Gate::authorize('create', PersonalBirthday::class);
            PersonalBirthday::create($attributes);
        }

        $this->resetBirthdayForm();
        $this->showBirthdayForm = false;

        Notification::make()->title('Anniversaire enregistré.')->success()->send();
    }

    public function deleteBirthday(int $birthdayId): void
    {
        $birthday = PersonalBirthday::query()->where('user_id', Auth::id())->findOrFail($birthdayId);
        Gate::authorize('delete', $birthday);

        $birthday->delete();
    }

    private function resetBirthdayForm(): void
    {
        $this->editingBirthdayId = null;
        $this->birthdayName = '';
        $this->birthdayDate = null;
        $this->birthdayNotes = null;
    }
}
