<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPhoto;
use App\Services\StudentPhotoManager;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

class Trombinoscope extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?string $navigationLabel = 'Trombinoscope';

    protected static ?string $title = 'Trombinoscope';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.trombinoscope';

    #[Url]
    public ?int $schoolClassId = null;

    public ?int $managingStudentId = null;

    public ?UploadedFile $newPhoto = null;

    public function mount(): void
    {
        if ($this->schoolClassId) {
            return;
        }

        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->value('id');
    }

    public function updatedSchoolClassId(): void
    {
        $this->managingStudentId = null;
        $this->newPhoto = null;
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    public function getSchoolClassesProperty(): Collection
    {
        return SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudentsProperty(): Collection
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return collect();
        }

        return $schoolClass->allStudents()
            ->where('is_archived', false)
            ->with('currentPhoto')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function toggleManaging(int $studentId): void
    {
        $this->managingStudentId = $this->managingStudentId === $studentId ? null : $studentId;
        $this->newPhoto = null;
    }

    /**
     * @return Collection<int, StudentPhoto>
     */
    public function getPhotoHistoryProperty(): Collection
    {
        if (! $this->managingStudentId) {
            return collect();
        }

        return StudentPhoto::query()
            ->where('student_id', $this->managingStudentId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function uploadPhoto(): void
    {
        if (! $this->managingStudentId || ! $this->newPhoto) {
            return;
        }

        $this->validate([
            'newPhoto' => ['image', 'max:5120'],
        ]);

        $student = $this->getStudentsProperty()->firstWhere('id', $this->managingStudentId);

        if (! $student) {
            return;
        }

        $path = $this->newPhoto->store('students', 'public');

        app(StudentPhotoManager::class)->setPhoto($student, $path);

        $this->newPhoto = null;

        Notification::make()->title('Photo enregistrée')->success()->send();
    }

    public function restorePhoto(int $photoId): void
    {
        $photo = StudentPhoto::query()->where('user_id', Auth::id())->find($photoId);

        if (! $photo) {
            return;
        }

        app(StudentPhotoManager::class)->restore($photo);

        Notification::make()->title('Photo restaurée')->success()->send();
    }

    public function deletePhoto(int $photoId): void
    {
        $photo = StudentPhoto::query()->where('user_id', Auth::id())->find($photoId);

        if (! $photo) {
            return;
        }

        app(StudentPhotoManager::class)->deletePermanently($photo);
    }

    public function downloadPdf()
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return null;
        }

        $filename = 'trombinoscope-'.str($schoolClass->name)->slug().'.pdf';
        $pdf = Pdf::loadView('pdf.trombinoscope', ['schoolClass' => $schoolClass, 'students' => $this->getStudentsProperty()]);

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }
}
