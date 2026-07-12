<?php

namespace App\Filament\Pages;

use App\Models\Import;
use App\Models\SchoolClass;
use App\Services\StudentImporter;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ImportStudents extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Importer des élèves';

    protected static ?string $title = 'Importer des élèves';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.import-students';

    public ?array $data = [];

    public string $phase = 'upload';

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<int, array<int, mixed>> */
    public array $rawRows = [];

    public ?string $originalFilename = null;

    /** @var array<string, int> */
    public array $mapping = [];

    public ?int $defaultSchoolClassId = null;

    /** @var array<int, array{row_number:int, data:array, school_class_id:?int, is_duplicate:bool, errors:array}> */
    public array $previewRows = [];

    public ?Import $completedImport = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Fichier')
                        ->schema([
                            Select::make('school_class_id')
                                ->label('Classe par défaut')
                                ->helperText('Utilisée si le fichier ne contient pas de colonne « Classe », ou si une valeur de la colonne ne correspond à aucune classe existante.')
                                ->options(fn () => SchoolClass::query()
                                    ->where('user_id', Auth::id())
                                    ->where('is_archived', false)
                                    ->pluck('name', 'id'))
                                ->searchable(),

                            FileUpload::make('file')
                                ->label('Fichier CSV ou Excel')
                                ->acceptedFileTypes([
                                    'text/csv',
                                    'text/plain',
                                    'application/csv',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->maxSize(5120)
                                ->storeFiles(false)
                                ->live()
                                ->afterStateUpdated(function ($state) {
                                    $this->headers = [];
                                    $this->rawRows = [];
                                    $this->originalFilename = null;

                                    if (! $state) {
                                        return;
                                    }

                                    $this->originalFilename = $state->getClientOriginalName();

                                    $parsed = app(StudentImporter::class)->parseFile($state->getRealPath());
                                    $this->headers = $parsed['headers'];
                                    $this->rawRows = $parsed['rows'];
                                })
                                ->required()
                                ->columnSpanFull(),
                        ]),

                    Step::make('Colonnes')
                        ->schema(fn () => $this->headers === []
                            ? [
                                Placeholder::make('no_file')
                                    ->label('')
                                    ->content('Téléversez un fichier à l\'étape précédente pour détecter ses colonnes.'),
                            ]
                            : collect($this->headers)
                                ->map(fn (string $header, int $index) => Select::make("mapping.{$index}")
                                    ->key("mapping-{$index}")
                                    ->label($header !== '' ? $header : 'Colonne '.($index + 1))
                                    ->options(collect(StudentImporter::FIELDS)
                                        ->map(fn (array $group) => collect($group)
                                            ->mapWithKeys(fn (array $field, string $key) => [$key => $field['label']])
                                            ->all())
                                        ->all())
                                    ->placeholder('Ignorer cette colonne'))
                                ->values()
                                ->all()),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit">
                            Analyser le fichier
                        </x-filament::button>
                        BLADE))),
            ])
            ->statePath('data');
    }

    public function analyze(): void
    {
        $state = $this->form->getState();

        $mapping = [];
        foreach ($state['mapping'] ?? [] as $index => $field) {
            if ($field) {
                $mapping[$field] = (int) $index;
            }
        }

        $hasStudentName = isset($mapping['full_name'])
            || (isset($mapping['first_name']) && isset($mapping['last_name']));

        if (! $hasStudentName) {
            Notification::make()
                ->title('Associez au moins le nom complet, ou le nom et le prénom séparément, à une colonne du fichier.')
                ->danger()
                ->send();

            return;
        }

        $this->mapping = $mapping;
        $this->defaultSchoolClassId = $state['school_class_id'] ? (int) $state['school_class_id'] : null;

        $this->previewRows = app(StudentImporter::class)->preview(
            Auth::user(),
            $this->rawRows,
            $this->mapping,
            $this->defaultSchoolClassId,
        );

        $classNames = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->pluck('name', 'id');

        $this->previewRows = collect($this->previewRows)
            ->map(function (array $row) use ($classNames) {
                $row['school_class_name'] = $row['school_class_id']
                    ? ($classNames[$row['school_class_id']] ?? '—')
                    : '—';

                return $row;
            })
            ->all();

        $this->phase = 'preview';
    }

    public function confirmImport(): void
    {
        $this->completedImport = app(StudentImporter::class)->execute(
            Auth::user(),
            $this->originalFilename ?? 'import.csv',
            $this->mapping,
            $this->defaultSchoolClassId,
            $this->previewRows,
        );

        $this->phase = 'report';
    }

    public function cancelCompletedImport(): void
    {
        if (! $this->completedImport) {
            return;
        }

        app(StudentImporter::class)->cancel($this->completedImport);
        $this->completedImport->refresh();
    }

    public function cancelPastImport(int $importId): void
    {
        $import = Import::query()->where('user_id', Auth::id())->findOrFail($importId);

        app(StudentImporter::class)->cancel($import);
    }

    public function restart(): void
    {
        $this->phase = 'upload';
        $this->headers = [];
        $this->rawRows = [];
        $this->originalFilename = null;
        $this->mapping = [];
        $this->defaultSchoolClassId = null;
        $this->previewRows = [];
        $this->completedImport = null;
        $this->data = [];
        $this->form->fill();
    }

    /**
     * @return Collection<int, Import>
     */
    public function getRecentImportsProperty(): Collection
    {
        return Import::query()
            ->where('user_id', Auth::id())
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getPreviewSummary(): array
    {
        return [
            'total' => count($this->previewRows),
            'valid' => collect($this->previewRows)->filter(fn ($row) => empty($row['errors']) && ! $row['is_duplicate'])->count(),
            'duplicates' => collect($this->previewRows)->filter(fn ($row) => $row['is_duplicate'])->count(),
            'errors' => collect($this->previewRows)->filter(fn ($row) => ! empty($row['errors']))->count(),
        ];
    }
}
