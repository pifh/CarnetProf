<?php

namespace App\Filament\Pages;

use App\Models\DisciplineEntry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\DisciplineTracker;
use App\Support\DisciplineCategories;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DisciplineTracking extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Oublis & discipline';

    protected static ?string $title = 'Oublis & discipline';

    protected static ?int $navigationSort = 32;

    protected string $view = 'filament.pages.discipline-tracking';

    public ?int $schoolClassId = null;

    /** @var array<string, int> */
    public array $thresholds = [];

    public ?int $historyStudentId = null;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->value('id');

        $this->thresholds = Auth::user()->disciplineThresholdsOrDefault();
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryLabelsProperty(): array
    {
        return DisciplineCategories::labels();
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

    public function getCurrentSchoolClassProperty(): ?SchoolClass
    {
        return $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudentsProperty(): Collection
    {
        $schoolClass = $this->currentSchoolClass;

        if (! $schoolClass) {
            return collect();
        }

        return $schoolClass->students()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<string, array{total: int, trip: int}> keyed by "studentId|category"
     */
    public function getCountsProperty(): Collection
    {
        $schoolClass = $this->currentSchoolClass;

        if (! $schoolClass) {
            return collect();
        }

        return app(DisciplineTracker::class)->countsForClass($schoolClass);
    }

    public function countsFor(int $studentId, string $category): array
    {
        return $this->counts->get($studentId.'|'.$category, ['total' => 0, 'trip' => 0]);
    }

    public function log(int $studentId, string $category): void
    {
        $student = Student::query()->where('user_id', Auth::id())->findOrFail($studentId);

        app(DisciplineTracker::class)->logEntry($student, $category);
    }

    public function resetStudent(int $studentId, string $category): void
    {
        $student = Student::query()->where('user_id', Auth::id())->findOrFail($studentId);

        app(DisciplineTracker::class)->resetStudent($student, $category);

        Notification::make()->title('Compteur réinitialisé pour '.$student->full_name.'.')->success()->send();
    }

    public function resetClass(string $category): void
    {
        $schoolClass = SchoolClass::query()->where('user_id', Auth::id())->findOrFail($this->schoolClassId);

        app(DisciplineTracker::class)->resetClass($schoolClass, $category);

        Notification::make()->title('Compteur réinitialisé pour toute la classe.')->success()->send();
    }

    public function saveThresholds(): void
    {
        $this->validate([
            'thresholds' => ['array'],
            'thresholds.*' => ['required', 'integer', 'min:1'],
        ]);

        Auth::user()->update(['discipline_thresholds' => $this->thresholds]);

        Notification::make()->title('Seuils enregistrés.')->success()->send();
    }

    public function deleteEntry(int $entryId): void
    {
        $entry = DisciplineEntry::query()->where('user_id', Auth::id())->findOrFail($entryId);

        Gate::authorize('delete', $entry);

        $entry->delete();

        Notification::make()->title('Entrée supprimée.')->success()->send();
    }

    public function showHistory(int $studentId): void
    {
        $this->historyStudentId = $studentId;
    }

    public function closeHistory(): void
    {
        $this->historyStudentId = null;
    }

    public function getHistoryStudentProperty(): ?Student
    {
        if (! $this->historyStudentId) {
            return null;
        }

        return Student::query()
            ->where('user_id', Auth::id())
            ->with('disciplineEntries')
            ->find($this->historyStudentId);
    }

    /**
     * @return Collection<string, Collection<int, DisciplineEntry>>
     */
    public function getHistoryByCategoryProperty(): Collection
    {
        return $this->historyStudent?->disciplineEntries->groupBy('category') ?? collect();
    }
}
