<?php

namespace App\Filament\Pages;

use App\Models\RandomPick;
use App\Models\RandomPickSession;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use BackedEnum;
use Filament\Notifications\Notification;
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

    public ?int $subjectId = null;

    public ?int $currentSessionId = null;

    public string $newSessionName = '';

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

        $this->syncSubjectId();
    }

    public function updatedSchoolClassId(): void
    {
        $this->closeSession();
        $this->syncSubjectId();
    }

    public function updatedSubjectId(): void
    {
        $this->closeSession();
    }

    private function syncSubjectId(): void
    {
        $subjectIds = $this->getSubjectsProperty()->pluck('id');

        if (! $subjectIds->contains($this->subjectId)) {
            $this->subjectId = $subjectIds->first();
        }
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
     * @return Collection<int, Subject>
     */
    public function getSubjectsProperty(): Collection
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        return $schoolClass?->subjects()->orderBy('name')->get() ?? collect();
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
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Every tirage already started for this classe (and matière, when the
     * class has several) — most recent first, so a teacher can pick up
     * exactly where they left off instead of losing their progress.
     *
     * @return Collection<int, RandomPickSession>
     */
    public function getSessionsProperty(): Collection
    {
        if (! $this->schoolClassId) {
            return collect();
        }

        $query = RandomPickSession::query()
            ->where('user_id', Auth::id())
            ->where('school_class_id', $this->schoolClassId)
            ->withCount('picks');

        $this->subjectId ? $query->where('subject_id', $this->subjectId) : $query->whereNull('subject_id');

        return $query->latest()->get();
    }

    public function createSession(): void
    {
        if (blank($this->newSessionName) || ! $this->schoolClassId) {
            Notification::make()
                ->title("Merci d'indiquer un nom pour ce tirage.")
                ->danger()
                ->send();

            return;
        }

        $session = new RandomPickSession([
            'school_class_id' => $this->schoolClassId,
            'subject_id' => $this->subjectId,
            'name' => $this->newSessionName,
        ]);
        $session->user_id = Auth::id();
        $session->save();

        $this->newSessionName = '';
        $this->openSession($session->id);
    }

    public function openSession(int $sessionId): void
    {
        $session = RandomPickSession::query()
            ->where('user_id', Auth::id())
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return;
        }

        $this->currentSessionId = $session->id;
        $this->excludedStudentIds = [];
        $this->lastPickedStudentId = null;

        // Resuming a tirage must not repeat someone already interrogated in
        // it, so the round picks up exactly where it was left off.
        $this->pickedStudentIdsThisRound = RandomPick::query()
            ->where('random_pick_session_id', $session->id)
            ->pluck('student_id')
            ->unique()
            ->values()
            ->all();
    }

    public function closeSession(): void
    {
        $this->currentSessionId = null;
        $this->excludedStudentIds = [];
        $this->pickedStudentIdsThisRound = [];
        $this->lastPickedStudentId = null;
    }

    public function deleteSession(int $sessionId): void
    {
        RandomPickSession::query()
            ->where('user_id', Auth::id())
            ->where('id', $sessionId)
            ->delete();

        if ($this->currentSessionId === $sessionId) {
            $this->closeSession();
        }
    }

    /**
     * @return Collection<int, array{student: Student, count: int}>
     */
    public function getSessionHistoryProperty(): Collection
    {
        if (! $this->currentSessionId) {
            return collect();
        }

        $counts = RandomPick::query()
            ->where('random_pick_session_id', $this->currentSessionId)
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
        if (! $this->currentSessionId) {
            return;
        }

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

        $pick = new RandomPick([
            'student_id' => $student->id,
            'school_class_id' => $this->schoolClassId,
            'random_pick_session_id' => $this->currentSessionId,
        ]);
        $pick->user_id = Auth::id();
        $pick->save();
    }

    public function resetRound(): void
    {
        $this->pickedStudentIdsThisRound = [];
        $this->lastPickedStudentId = null;
    }
}
