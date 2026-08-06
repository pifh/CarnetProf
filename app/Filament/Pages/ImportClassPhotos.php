<?php

namespace App\Filament\Pages;

use App\Models\PhotoImport;
use App\Models\PhotoImportPhoto;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\ClassPhotoPdfExtractor;
use App\Services\StudentPhotoManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use RuntimeException;

class ImportClassPhotos extends Page
{
    use WithFileUploads;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.import-class-photos';

    public SchoolClass $schoolClass;

    /** @var 'upload'|'review'|'report' */
    public string $phase = 'upload';

    public ?UploadedFile $pdfFile = null;

    public ?PhotoImport $photoImport = null;

    /** @var array<int, int|null> photoImportPhotoId => studentId, ephemeral wizard state */
    public array $assignments = [];

    public ?string $analysisError = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/trombinoscope/{schoolClass}/import';
    }

    public function mount(SchoolClass $schoolClass): void
    {
        abort_unless($schoolClass->user_id === Auth::id(), 403);

        $this->schoolClass = $schoolClass;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Importer les photos — '.$this->schoolClass->name;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getRosterProperty(): Collection
    {
        return $this->schoolClass->allStudents()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, PhotoImportPhoto>
     */
    public function getCandidatesProperty(): Collection
    {
        return $this->photoImport?->photos()->orderBy('position')->get() ?? collect();
    }

    public function analyze(): void
    {
        $this->analysisError = null;

        $this->validate([
            'pdfFile' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $uuid = (string) Str::uuid();
        $scratchDir = "photo-imports/{$uuid}";

        $this->pdfFile->storeAs($scratchDir, 'source.pdf', 'local');
        $pdfPath = Storage::disk('local')->path("{$scratchDir}/source.pdf");
        $outputDir = Storage::disk('local')->path($scratchDir);

        try {
            $candidates = app(ClassPhotoPdfExtractor::class)->extract($pdfPath, $outputDir);
        } catch (RuntimeException $exception) {
            report($exception);
            Storage::disk('local')->deleteDirectory($scratchDir);
            $this->analysisError = "L'extraction des photos a échoué. Réessayez, ou ajoutez les photos une par une depuis le trombinoscope.";

            return;
        }

        if ($candidates === []) {
            Storage::disk('local')->deleteDirectory($scratchDir);
            $this->analysisError = "Aucune photo exploitable n'a été trouvée dans ce PDF. Il s'agit peut-être d'une image scannée à plat plutôt que de photos intégrées séparément — vous pouvez ajouter les photos une par une depuis le trombinoscope.";

            return;
        }

        $photoImport = new PhotoImport([
            'school_class_id' => $this->schoolClass->id,
            'original_filename' => $this->pdfFile->getClientOriginalName(),
            'status' => 'pending',
        ]);
        $photoImport->user_id = Auth::id();
        $photoImport->save();

        $roster = $this->getRosterProperty();
        $this->assignments = [];

        foreach (array_values($candidates) as $index => $sourcePath) {
            $publicPath = "photo-imports/{$uuid}/".basename($sourcePath);
            Storage::disk('public')->put($publicPath, file_get_contents($sourcePath));

            $photo = PhotoImportPhoto::create([
                'photo_import_id' => $photoImport->id,
                'position' => $index,
                'path' => $publicPath,
            ]);

            $this->assignments[$photo->id] = $roster->get($index)?->id;
        }

        Storage::disk('local')->deleteDirectory($scratchDir);

        $this->photoImport = $photoImport;
        $this->pdfFile = null;
        $this->phase = 'review';
    }

    public function confirmImport(): void
    {
        if (! $this->photoImport) {
            return;
        }

        $candidates = $this->getCandidatesProperty();
        $scratchDir = $candidates->isNotEmpty() ? dirname($candidates->first()->path) : null;

        $roster = $this->getRosterProperty()->keyBy('id');
        $assigned = 0;

        foreach ($candidates as $photo) {
            $studentId = $this->assignments[$photo->id] ?? null;
            $student = $studentId ? $roster->get($studentId) : null;

            if ($student) {
                $newPath = 'students/'.Str::uuid().'.'.pathinfo($photo->path, PATHINFO_EXTENSION);
                Storage::disk('public')->move($photo->path, $newPath);

                app(StudentPhotoManager::class)->setPhoto($student, $newPath, source: 'pdf_import');
                $photo->update(['student_id' => $student->id]);
                $assigned++;
            } else {
                Storage::disk('public')->delete($photo->path);
            }
        }

        if ($scratchDir) {
            Storage::disk('public')->deleteDirectory($scratchDir);
        }

        $this->photoImport->update(['status' => 'completed']);

        Notification::make()
            ->title($assigned.' photo(s) enregistrée(s)')
            ->success()
            ->send();

        $this->phase = 'report';
    }

    public function restart(): void
    {
        $this->cleanupPendingImport();

        $this->phase = 'upload';
        $this->pdfFile = null;
        $this->photoImport = null;
        $this->assignments = [];
        $this->analysisError = null;
    }

    private function cleanupPendingImport(): void
    {
        if (! $this->photoImport || $this->photoImport->status !== 'pending') {
            return;
        }

        $candidates = $this->getCandidatesProperty();
        $scratchDir = $candidates->isNotEmpty() ? dirname($candidates->first()->path) : null;

        foreach ($candidates as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        if ($scratchDir) {
            Storage::disk('public')->deleteDirectory($scratchDir);
        }

        $this->photoImport->delete();
    }
}
