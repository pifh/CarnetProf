<?php

namespace App\Livewire;

use App\Filament\Pages\Averages as AveragesPage;
use App\Filament\Resources\StudentEvents\StudentEventResource;
use App\Models\Appreciation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentEvent;
use App\Models\Subject;
use App\Models\Term;
use App\Services\DisciplineTracker;
use App\Services\GradeCalculator;
use App\Support\DisciplineCategories;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The "fiche élève" synthesis card: grades (per-subject average + individual
 * notes), special needs, discipline counters with dates, an appréciation
 * (editable), photo, class, and recent event history for one student.
 * Embedded both on the Conseil de classe page (class/term fixed by the host
 * page, allowClassSwitch=false) and inline in the Événements élèves
 * create/edit form (no host context, allowClassSwitch=true so the teacher
 * can pick which of the student's classes — home or groupe classe — to view
 * grades/discipline for, appreciationReadOnly=true since editing an
 * appreciation from an unrelated event form isn't the intended flow — the
 * fiche there is for context, not editing). Always keeps its own internal
 * term selector since the events form has no term concept at all.
 */
class StudentRecordCard extends Component
{
    public int $studentId;

    public ?int $schoolClassId = null;

    public ?int $termId = null;

    public ?int $subjectId = null;

    public bool $allowClassSwitch = false;

    public bool $appreciationReadOnly = false;

    public function mount(int $studentId, ?int $schoolClassId = null, ?int $defaultTermId = null, bool $allowClassSwitch = false, bool $appreciationReadOnly = false): void
    {
        $this->studentId = $studentId;
        $this->allowClassSwitch = $allowClassSwitch;
        $this->appreciationReadOnly = $appreciationReadOnly;
        $this->termId = $defaultTermId;

        $student = $this->student;

        $this->schoolClassId = ($schoolClassId && $this->classOptions->contains('id', $schoolClassId))
            ? $schoolClassId
            : $student?->school_class_id;

        $this->syncSubjectId();
    }

    public function getStudentProperty(): ?Student
    {
        return Student::query()
            ->where('user_id', Auth::id())
            ->with(['schoolClass', 'currentPhoto'])
            ->find($this->studentId);
    }

    /**
     * Home class + any groupe classe memberships — the set of classes this
     * student's grades/discipline can meaningfully be viewed through.
     *
     * @return Collection<int, SchoolClass>
     */
    public function getClassOptionsProperty(): Collection
    {
        $student = $this->student;

        if (! $student) {
            return collect();
        }

        return $student->groupClasses()->where('is_archived', false)->get()
            ->push($student->schoolClass)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function getSchoolClassProperty(): ?SchoolClass
    {
        return $this->classOptions->firstWhere('id', $this->schoolClassId);
    }

    public function updatedSchoolClassId(): void
    {
        if (! $this->classOptions->contains('id', $this->schoolClassId)) {
            $this->schoolClassId = $this->student?->school_class_id;
        }

        $this->syncSubjectId();
    }

    /**
     * @return Collection<int, Subject>
     */
    public function getSubjectsProperty(): Collection
    {
        return $this->schoolClass?->subjects()->orderBy('name')->get() ?? collect();
    }

    private function syncSubjectId(): void
    {
        $subjectIds = $this->subjects->pluck('id');

        if (! $subjectIds->contains($this->subjectId)) {
            $this->subjectId = $subjectIds->first();
        }
    }

    /**
     * Every top-level term immediately followed by its own sub-periods —
     * for the period select.
     *
     * @return Collection<int, Term>
     */
    public function getTermsProperty(): Collection
    {
        return Term::hierarchicalForTeacher();
    }

    public function getTermProperty(): ?Term
    {
        return $this->termId ? $this->terms->firstWhere('id', $this->termId) : null;
    }

    /**
     * @return Collection<int, array{subject: ?Subject, label: string, average: ?float, grades: Collection}>
     */
    public function getSubjectAveragesProperty(): Collection
    {
        $student = $this->student;
        $schoolClass = $this->schoolClass;

        if (! $student || ! $schoolClass) {
            return collect();
        }

        $calculator = app(GradeCalculator::class);
        $subjects = $this->subjects;

        $rows = $subjects->map(fn (Subject $subject) => [
            'subject' => $subject,
            'label' => $subject->name,
            'average' => $calculator->studentAverage($student, $schoolClass, $this->term, $subject),
            'grades' => $calculator->gradesFor($student, $schoolClass, $this->term, $subject),
        ]);

        if ($subjects->count() >= 2) {
            $rows->push([
                'subject' => null,
                'label' => 'Moyenne générale',
                'average' => $calculator->studentAverage($student, $schoolClass, $this->term, null),
                'grades' => collect(),
            ]);
        } elseif ($subjects->isEmpty()) {
            $rows->push([
                'subject' => null,
                'label' => 'Moyenne',
                'average' => $calculator->studentAverage($student, $schoolClass, $this->term, null),
                'grades' => $calculator->gradesFor($student, $schoolClass, $this->term, null),
            ]);
        }

        return $rows;
    }

    /**
     * @return array<string, array{total: int, trip: int}>
     */
    public function getDisciplineCountsProperty(): array
    {
        $student = $this->student;
        $schoolClass = $this->schoolClass;

        if (! $student || ! $schoolClass) {
            return [];
        }

        $tracker = app(DisciplineTracker::class);

        return collect(DisciplineCategories::ALL)
            ->mapWithKeys(fn (string $category) => [$category => $tracker->countsForStudent($student, $schoolClass, $category)])
            ->all();
    }

    /**
     * @return Collection<string, Collection>
     */
    public function getDisciplineEntriesProperty(): Collection
    {
        $student = $this->student;
        $schoolClass = $this->schoolClass;

        if (! $student || ! $schoolClass) {
            return collect();
        }

        return app(DisciplineTracker::class)->entriesForStudent($student, $schoolClass);
    }

    public function getAppreciationProperty(): ?Appreciation
    {
        return $this->findAppreciation();
    }

    private function findAppreciation(): ?Appreciation
    {
        $schoolClass = $this->schoolClass;

        if (! $schoolClass) {
            return null;
        }

        return Appreciation::query()
            ->where('student_id', $this->studentId)
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $this->subjectId)
            ->where('term_id', $this->termId)
            ->first();
    }

    public function updateAppreciation(?string $value): void
    {
        if ($this->appreciationReadOnly) {
            return;
        }

        $appreciation = $this->findOrNewAppreciation();

        if (! $appreciation) {
            return;
        }

        $appreciation->content = trim((string) $value) ?: null;
        $appreciation->save();
    }

    public function toggleAppreciationDraft(): void
    {
        if ($this->appreciationReadOnly) {
            return;
        }

        $appreciation = $this->findAppreciation();

        if (! $appreciation) {
            return;
        }

        $appreciation->is_draft = ! $appreciation->is_draft;
        $appreciation->save();
    }

    private function findOrNewAppreciation(): ?Appreciation
    {
        $schoolClass = $this->schoolClass;

        if (! $schoolClass) {
            return null;
        }

        $appreciation = Appreciation::query()->firstOrNew([
            'student_id' => $this->studentId,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $this->subjectId,
            'term_id' => $this->termId,
        ]);
        $appreciation->user_id = Auth::id();

        return $appreciation;
    }

    /**
     * @return Collection<int, StudentEvent>
     */
    public function getRecentEventsProperty(): Collection
    {
        return $this->student?->events()->limit(8)->get() ?? collect();
    }

    public function getEventsIndexUrlProperty(): string
    {
        return StudentEventResource::getUrl('index');
    }

    public function getAveragesPageUrlProperty(): string
    {
        return AveragesPage::getUrl();
    }

    public function render()
    {
        return view('livewire.student-record-card');
    }
}
