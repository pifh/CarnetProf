<?php

namespace App\Filament\Pages;

use App\Models\RandomPick;
use App\Models\SchoolClass;
use App\Models\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class RandomPicker extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Tirage aléatoire';

    protected static ?string $title = 'Tirage aléatoire';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.random-picker';

    public ?int $schoolClassId = null;

    /** @var array<int, int> */
    public array $excludedStudentIds = [];

    /** @var array<int, int> */
    public array $pickedStudentIdsThisRound = [];

    public ?int $lastPickedStudentId = null;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->value('id');
    }

    public function updatedSchoolClassId(): void
    {
        $this->excludedStudentIds = [];
        $this->resetRound();
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

        return $schoolClass->students()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, array{student: Student, count: int}>
     */
    public function getHistoryProperty(): Collection
    {
        if (! $this->schoolClassId) {
            return collect();
        }

        $counts = RandomPick::query()
            ->where('school_class_id', $this->schoolClassId)
            ->selectRaw('student_id, count(*) as aggregate')
            ->groupBy('student_id')
            ->pluck('aggregate', 'student_id');

        return $this->getStudentsProperty()->map(fn (Student $student) => [
            'student' => $student,
            'count' => $counts->get($student->id, 0),
        ])->sortByDesc('count')->values();
    }

    public function toggleExcluded(int $studentId): void
    {
        if (in_array($studentId, $this->excludedStudentIds, true)) {
            $this->excludedStudentIds = array_values(array_diff($this->excludedStudentIds, [$studentId]));
        } else {
            $this->excludedStudentIds[] = $studentId;
        }
    }

    public function pick(): void
    {
        $pool = $this->getStudentsProperty()
            ->reject(fn (Student $student) => in_array($student->id, $this->excludedStudentIds, true));

        if ($pool->isEmpty()) {
            return;
        }

        $remaining = $pool->reject(fn (Student $student) => in_array($student->id, $this->pickedStudentIdsThisRound, true));

        if ($remaining->isEmpty()) {
            $this->pickedStudentIdsThisRound = [];
            $remaining = $pool;
        }

        $student = $remaining->random();

        $this->lastPickedStudentId = $student->id;
        $this->pickedStudentIdsThisRound[] = $student->id;

        $pick = new RandomPick(['student_id' => $student->id, 'school_class_id' => $this->schoolClassId]);
        $pick->user_id = Auth::id();
        $pick->save();
    }

    public function resetRound(): void
    {
        $this->pickedStudentIdsThisRound = [];
        $this->lastPickedStudentId = null;
    }
}
