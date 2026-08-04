<?php

namespace App\Filament\Pages;

use App\Models\BackupDestination;
use App\Services\BackupDestinationDiskFactory;
use App\Services\BackupProviders\BackupProviderRegistry;
use App\Services\PersonalDataExporter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

class MyBackup extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static ?string $navigationLabel = 'Ma sauvegarde';

    protected static ?string $title = 'Ma sauvegarde';

    protected static ?int $navigationSort = 87;

    protected string $view = 'filament.pages.my-backup';

    public bool $showForm = false;

    public ?int $editingDestinationId = null;

    public string $provider = 'ftp';

    public string $label = '';

    /** @var array<string, mixed> */
    public array $credentials = [];

    /**
     * @return Collection<int, BackupDestination>
     */
    public function getDestinationsProperty(): Collection
    {
        return BackupDestination::query()->where('user_id', Auth::id())->orderBy('label')->get();
    }

    /**
     * @return array<string, string>
     */
    public function getProvidersProperty(): array
    {
        return collect(BackupProviderRegistry::all())->map(fn ($provider) => $provider->label())->all();
    }

    public function addDestination(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editDestination(int $destinationId): void
    {
        $destination = BackupDestination::query()->where('user_id', Auth::id())->findOrFail($destinationId);

        Gate::authorize('update', $destination);

        $this->editingDestinationId = $destination->id;
        $this->provider = $destination->provider;
        $this->label = $destination->label;
        $this->credentials = $destination->credentials ?? [];
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function save(): void
    {
        $this->validate($this->rules());

        $attributes = [
            'user_id' => Auth::id(),
            'provider' => $this->provider,
            'label' => $this->label,
            'credentials' => $this->credentials,
        ];

        if ($this->editingDestinationId) {
            $destination = BackupDestination::query()->where('user_id', Auth::id())->findOrFail($this->editingDestinationId);
            Gate::authorize('update', $destination);
            $destination->update($attributes);
        } else {
            Gate::authorize('create', BackupDestination::class);
            BackupDestination::create($attributes + ['is_active' => true]);
        }

        $this->resetForm();
        $this->showForm = false;

        Notification::make()->title('Destination enregistrée.')->success()->send();
    }

    public function toggleActive(int $destinationId): void
    {
        $destination = BackupDestination::query()->where('user_id', Auth::id())->findOrFail($destinationId);
        Gate::authorize('update', $destination);

        $destination->update(['is_active' => ! $destination->is_active]);
    }

    public function deleteDestination(int $destinationId): void
    {
        $destination = BackupDestination::query()->where('user_id', Auth::id())->findOrFail($destinationId);
        Gate::authorize('delete', $destination);

        $destination->delete();
    }

    public function testConnection(int $destinationId): void
    {
        $destination = BackupDestination::query()->where('user_id', Auth::id())->findOrFail($destinationId);
        Gate::authorize('view', $destination);

        try {
            app(BackupDestinationDiskFactory::class)->make($destination)->directoryExists('');

            Notification::make()->title('Connexion réussie.')->success()->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()->title('Échec de la connexion. Vérifiez les identifiants.')->danger()->send();
        }
    }

    public function runBackup(): void
    {
        if ($this->getDestinationsProperty()->where('is_active', true)->isEmpty()) {
            Notification::make()->title('Ajoutez au moins une destination active avant de sauvegarder.')->warning()->send();

            return;
        }

        $results = app(PersonalDataExporter::class)->export();
        $failures = collect($results)->filter(fn ($ok) => ! $ok)->count();

        if ($failures === 0) {
            Notification::make()->title('Sauvegarde envoyée avec succès.')->success()->send();
        } else {
            Notification::make()
                ->title($failures.' destination(s) en échec sur '.count($results).'. Consultez les journaux du serveur.')
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return match ($this->provider) {
            'ftp' => [
                'label' => ['required', 'string', 'max:255'],
                'credentials.host' => ['required', 'string'],
                'credentials.port' => ['nullable', 'integer'],
                'credentials.username' => ['required', 'string'],
                'credentials.password' => ['nullable', 'string'],
                'credentials.root' => ['nullable', 'string'],
            ],
            'sftp' => [
                'label' => ['required', 'string', 'max:255'],
                'credentials.host' => ['required', 'string'],
                'credentials.port' => ['nullable', 'integer'],
                'credentials.username' => ['required', 'string'],
                'credentials.password' => ['nullable', 'string'],
                'credentials.private_key' => ['nullable', 'string'],
                'credentials.passphrase' => ['nullable', 'string'],
                'credentials.root' => ['nullable', 'string'],
            ],
            's3' => [
                'label' => ['required', 'string', 'max:255'],
                'credentials.key' => ['required', 'string'],
                'credentials.secret' => ['required', 'string'],
                'credentials.region' => ['nullable', 'string'],
                'credentials.bucket' => ['required', 'string'],
                'credentials.endpoint' => ['nullable', 'string'],
                'credentials.root' => ['nullable', 'string'],
            ],
            'webdav' => [
                'label' => ['required', 'string', 'max:255'],
                'credentials.base_uri' => ['required', 'string'],
                'credentials.username' => ['nullable', 'string'],
                'credentials.password' => ['nullable', 'string'],
            ],
            default => ['label' => ['required', 'string', 'max:255']],
        };
    }

    private function resetForm(): void
    {
        $this->editingDestinationId = null;
        $this->provider = 'ftp';
        $this->label = '';
        $this->credentials = [];
    }
}
