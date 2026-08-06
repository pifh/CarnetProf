<?php

namespace App\Filament\Pages;

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\GroupGeneration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradeCalculator;
use App\Services\GroupAssigner;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class GroupGenerator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Générateur de groupes';

    protected static ?string $title = 'Générateur de groupes';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.group-generator';

    public ?int $schoolClassId = null;

    public ?int $subjectId = null;

    public ?int $termId = null;

    public string $mode = 'count';

    public int $groupCount = 4;

    public int $groupSize = 4;

    /** @var array<int, int> */
    public array $excludedStudentIds = [];

    /** @var 'none'|'balance'|'homogeneous' */
    public string $levelMode = 'none';

    /** @var 'none'|'mixed_balanced'|'single_sex' */
    public string $genderMode = 'none';

    public bool $avoidRepeats = false;

    /** @var array<int, int> studentId => 0-based group index, ephemeral for this run */
    public array $lockedPlacements = [];

    /** @var array<int, array{0: int, 1: int}> */
    public array $keepTogetherPairs = [];

    /** @var array<int, array{0: int, 1: int}> */
    public array $keepApartPairs = [];

    public ?int $keepTogetherStudentA = null;

    public ?int $keepTogetherStudentB = null;

    public ?int $keepApartStudentA = null;

    public ?int $keepApartStudentB = null;

    /** @var array<int, int[]> */
    public array $generatedGroups = [];

    /** @var array<int, array{type: string, pair?: array{0: int, 1: int}, members?: int[]}> */
    public array $generationConflicts = [];

    public ?string $activityName = null;

    public bool $isGraded = false;

    public float $maxScore = 20;

    public float $coefficient = 1;

    /** @var array<int, float|string|null> groupIndex => score, ephemeral for this run */
    public array $groupScores = [];

    public ?int $viewingGenerationId = null;

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
        $this->excludedStudentIds = [];
        $this->generatedGroups = [];
        $this->generationConflicts = [];
        $this->lockedPlacements = [];
        $this->keepTogetherPairs = [];
        $this->keepApartPairs = [];
        $this->viewingGenerationId = null;
        $this->resetActivityState();
        $this->syncSubjectId();
    }

    public function updatedSubjectId(): void
    {
        $this->viewingGenerationId = null;
    }

    private function resetActivityState(): void
    {
        $this->activityName = null;
        $this->isGraded = false;
        $this->maxScore = 20;
        $this->coefficient = 1;
        $this->groupScores = [];
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
     * @return Collection<int, Term>
     */
    public function getTermsProperty(): Collection
    {
        return Term::query()
            ->where('user_id', Auth::id())
            ->orderBy('position')
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
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function getResolvedGroupCountProperty(): int
    {
        $poolCount = $this->getStudentsProperty()
            ->reject(fn (Student $student) => in_array($student->id, $this->excludedStudentIds, true))
            ->count();

        if ($poolCount === 0) {
            return 0;
        }

        return $this->mode === 'size'
            ? max(1, (int) ceil($poolCount / max(1, $this->groupSize)))
            : max(1, min($this->groupCount, $poolCount));
    }

    public function toggleExcluded(int $studentId): void
    {
        if (in_array($studentId, $this->excludedStudentIds, true)) {
            $this->excludedStudentIds = array_values(array_diff($this->excludedStudentIds, [$studentId]));

            return;
        }

        $this->excludedStudentIds[] = $studentId;
        unset($this->lockedPlacements[$studentId]);
        $this->keepTogetherPairs = $this->removePairsReferencing($this->keepTogetherPairs, $studentId);
        $this->keepApartPairs = $this->removePairsReferencing($this->keepApartPairs, $studentId);
    }

    public function addKeepTogetherPair(): void
    {
        $this->addPair('keepTogetherPairs', $this->keepTogetherStudentA, $this->keepTogetherStudentB);
        $this->keepTogetherStudentA = null;
        $this->keepTogetherStudentB = null;
    }

    public function removeKeepTogetherPair(int $index): void
    {
        unset($this->keepTogetherPairs[$index]);
        $this->keepTogetherPairs = array_values($this->keepTogetherPairs);
    }

    public function addKeepApartPair(): void
    {
        $this->addPair('keepApartPairs', $this->keepApartStudentA, $this->keepApartStudentB);
        $this->keepApartStudentA = null;
        $this->keepApartStudentB = null;
    }

    public function removeKeepApartPair(int $index): void
    {
        unset($this->keepApartPairs[$index]);
        $this->keepApartPairs = array_values($this->keepApartPairs);
    }

    private function addPair(string $property, ?int $a, ?int $b): void
    {
        if (! $a || ! $b || $a === $b) {
            return;
        }

        $key = GroupAssigner::pairKey($a, $b);
        $alreadyPresent = collect($this->{$property})
            ->contains(fn (array $pair) => GroupAssigner::pairKey((int) $pair[0], (int) $pair[1]) === $key);

        if (! $alreadyPresent) {
            $this->{$property}[] = [$a, $b];
        }
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $pairs
     * @return array<int, array{0: int, 1: int}>
     */
    private function removePairsReferencing(array $pairs, int $studentId): array
    {
        return array_values(array_filter($pairs, fn (array $pair) => ! in_array($studentId, $pair, true)));
    }

    public function generate(): void
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $pool = $this->getStudentsProperty()
            ->reject(fn (Student $student) => in_array($student->id, $this->excludedStudentIds, true))
            ->values();

        $this->generationConflicts = [];
        $this->groupScores = [];

        if (! $schoolClass || $pool->isEmpty()) {
            $this->generatedGroups = [];

            return;
        }

        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
        $calculator = app(GradeCalculator::class);

        $averages = $pool->mapWithKeys(fn (Student $student) => [
            $student->id => $calculator->studentAverage($student, $schoolClass, $term, $subject),
        ])->all();
        $sexes = $pool->mapWithKeys(fn (Student $student) => [$student->id => $student->sex])->all();

        $previousPairCounts = [];
        if ($this->avoidRepeats) {
            $history = GroupGeneration::query()->where('school_class_id', $this->schoolClassId);
            $this->subjectId ? $history->where('subject_id', $this->subjectId) : $history->whereNull('subject_id');
            $previousPairCounts = GroupAssigner::pairCountsFromHistory($history->get()->pluck('groups')->all());
        }

        $lockedPlacements = collect($this->lockedPlacements)
            ->filter(fn ($index) => $index !== null && $index !== '')
            ->mapWithKeys(fn ($index, $studentId) => [(int) $studentId => (int) $index])
            ->all();

        $result = app(GroupAssigner::class)->assign(
            studentIds: $pool->pluck('id')->all(),
            groupCount: $this->resolvedGroupCount,
            averages: $averages,
            sexes: $sexes,
            levelMode: $this->levelMode,
            genderMode: $this->genderMode,
            avoidRepeats: $this->avoidRepeats,
            previousPairCounts: $previousPairCounts,
            lockedPlacements: $lockedPlacements,
            keepTogetherPairs: $this->keepTogetherPairs,
            keepApartPairs: $this->keepApartPairs,
        );

        $this->generatedGroups = $result->groups;
        $this->generationConflicts = $result->unresolvedConflicts;
    }

    /**
     * @return array<int, array{name: string, students: Collection<int, Student>, average: ?float, sexCounts: array<string, int>}>
     */
    public function getGeneratedGroupsDisplayProperty(): array
    {
        $students = $this->getStudentsProperty();
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
        $calculator = app(GradeCalculator::class);

        return collect($this->generatedGroups)
            ->map(function (array $studentIds, int $index) use ($students, $schoolClass, $term, $subject, $calculator) {
                $groupStudents = collect($studentIds)
                    ->map(fn (int $id) => $students->firstWhere('id', $id))
                    ->filter()
                    ->values();

                $averages = $groupStudents
                    ->map(fn (Student $student) => $schoolClass ? $calculator->studentAverage($student, $schoolClass, $term, $subject) : null)
                    ->filter(fn (?float $average) => $average !== null);

                $sexCounts = ['f' => 0, 'm' => 0, 'other' => 0];
                foreach ($groupStudents as $student) {
                    if (isset($sexCounts[$student->sex])) {
                        $sexCounts[$student->sex]++;
                    }
                }

                return [
                    'name' => 'Groupe '.($index + 1),
                    'students' => $groupStudents,
                    'average' => $averages->isNotEmpty() ? round($averages->avg(), 2) : null,
                    'sexCounts' => $sexCounts,
                ];
            })
            ->values()
            ->all();
    }

    public function conflictLabel(array $conflict): string
    {
        return match ($conflict['type']) {
            'keep_together_vs_conflict' => "Deux élèves à garder ensemble sont aussi marqués incompatibles ou préplacés dans des groupes différents : ils n'ont pas pu être regroupés.",
            'mixed_cluster_vs_single_sex' => "Un groupe d'élèves à garder ensemble contient des sexes différents : la contrainte « non mixte » n'a pas pu s'appliquer à eux.",
            'keep_apart_unresolvable' => 'Deux élèves à séparer sont liés indirectement par des élèves à garder ensemble : ils se retrouvent dans le même groupe.',
            'together_split' => "Un groupe d'élèves à garder ensemble contenait des préplacements contradictoires : il a été scindé.",
            default => 'Une contrainte demandée n\'a pas pu être totalement respectée.',
        };
    }

    public function save(): void
    {
        if (empty($this->generatedGroups) || ! $this->schoolClassId) {
            return;
        }

        if (blank($this->activityName)) {
            Notification::make()
                ->title("Merci d'indiquer le nom de l'activité avant d'enregistrer les groupes.")
                ->danger()
                ->send();

            return;
        }

        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;

        if ($this->isGraded && ! $term) {
            Notification::make()
                ->title('Choisissez un trimestre pour pouvoir noter cette activité.')
                ->danger()
                ->send();

            return;
        }

        $evaluation = null;

        if ($this->isGraded) {
            $evaluation = new Evaluation([
                'school_class_id' => $this->schoolClassId,
                'subject_id' => $subject?->id,
                'term_id' => $term->id,
                'title' => $this->activityName,
                'exam_date' => now()->toDateString(),
                'coefficient' => $this->coefficient ?: 1,
                'max_score' => $this->maxScore ?: 20,
            ]);
            $evaluation->user_id = Auth::id();
            $evaluation->save();
        }

        $generation = new GroupGeneration([
            'school_class_id' => $this->schoolClassId,
            'subject_id' => $subject?->id,
            'activity_name' => $this->activityName,
            'term_id' => $term?->id,
            'evaluation_id' => $evaluation?->id,
            'mode' => $this->mode,
            'group_count' => $this->mode === 'count' ? $this->groupCount : null,
            'group_size' => $this->mode === 'size' ? $this->groupSize : null,
            'groups' => array_values($this->generatedGroups),
            'criteria' => [
                'level_mode' => $this->levelMode,
                'gender_mode' => $this->genderMode,
                'avoid_repeats' => $this->avoidRepeats,
                'excluded_student_ids' => $this->excludedStudentIds,
                'locked_placements' => $this->lockedPlacements,
                'keep_together_pairs' => $this->keepTogetherPairs,
                'keep_apart_pairs' => $this->keepApartPairs,
                'conflicts' => $this->generationConflicts,
            ],
        ]);
        $generation->user_id = Auth::id();
        $generation->save();

        foreach ($this->generatedGroups as $index => $studentIds) {
            $group = new StudentSubgroup([
                'school_class_id' => $this->schoolClassId,
                'subject_id' => $subject?->id,
                'name' => $this->activityName.' — Groupe '.($index + 1),
                'group_generation_id' => $generation->id,
            ]);
            $group->user_id = Auth::id();
            $group->save();
            $group->students()->sync($studentIds);

            if ($evaluation) {
                $score = $this->normalizeScore($this->groupScores[$index] ?? null, (float) $evaluation->max_score);

                foreach ($studentIds as $studentId) {
                    $grade = new Grade([
                        'evaluation_id' => $evaluation->id,
                        'student_id' => $studentId,
                        'score' => $score,
                        'status' => $score !== null ? 'graded' : 'not_graded',
                    ]);
                    $grade->user_id = Auth::id();
                    $grade->save();
                }
            }
        }

        Notification::make()
            ->title($evaluation ? 'Groupes enregistrés, archivés et notés' : 'Groupes enregistrés et archivés')
            ->success()
            ->send();

        $this->generatedGroups = [];
        $this->generationConflicts = [];
        $this->resetActivityState();
    }

    private function normalizeScore(float|string|null $value, float $maxScore): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return max(0, min((float) str_replace(',', '.', $value), $maxScore));
    }

    /**
     * @return Collection<int, GroupGeneration>
     */
    public function getHistoryProperty(): Collection
    {
        if (! $this->schoolClassId) {
            return collect();
        }

        $query = GroupGeneration::query()
            ->where('user_id', Auth::id())
            ->where('school_class_id', $this->schoolClassId)
            ->with(['subject', 'term', 'evaluation']);

        $this->subjectId ? $query->where('subject_id', $this->subjectId) : $query->whereNull('subject_id');

        return $query->latest()->limit(10)->get();
    }

    public function historySummary(GroupGeneration $generation): string
    {
        $criteria = $generation->criteria;
        $parts = [count($generation->groups).' groupe(s)'];

        $parts[] = match ($criteria['level_mode'] ?? 'none') {
            'balance' => 'niveaux équilibrés',
            'homogeneous' => 'groupes de niveau',
            default => null,
        };
        $parts[] = match ($criteria['gender_mode'] ?? 'none') {
            'mixed_balanced' => 'mixte équilibré',
            'single_sex' => 'non mixte',
            default => null,
        };
        if (! empty($criteria['avoid_repeats'])) {
            $parts[] = 'anti-répétition';
        }
        if (! empty($criteria['keep_together_pairs'])) {
            $parts[] = count($criteria['keep_together_pairs']).' binôme(s) gardé(s)';
        }
        if (! empty($criteria['keep_apart_pairs'])) {
            $parts[] = count($criteria['keep_apart_pairs']).' binôme(s) séparé(s)';
        }

        return collect($parts)->filter()->implode(' · ');
    }

    public function deleteGeneration(int $generationId): void
    {
        GroupGeneration::query()
            ->where('user_id', Auth::id())
            ->where('school_class_id', $this->schoolClassId)
            ->where('id', $generationId)
            ->delete();

        if ($this->viewingGenerationId === $generationId) {
            $this->viewingGenerationId = null;
        }
    }

    public function toggleViewGeneration(int $generationId): void
    {
        $this->viewingGenerationId = $this->viewingGenerationId === $generationId ? null : $generationId;
    }

    /**
     * @return array<int, array{name: string, students: Collection<int, Student>, sexCounts: array<string, int>, score: ?float}>
     */
    public function getViewedGenerationGroupsProperty(): array
    {
        if (! $this->viewingGenerationId) {
            return [];
        }

        $generation = GroupGeneration::query()
            ->where('user_id', Auth::id())
            ->where('school_class_id', $this->schoolClassId)
            ->with('evaluation')
            ->find($this->viewingGenerationId);

        if (! $generation) {
            return [];
        }

        $studentIds = collect($generation->groups)->flatten()->all();
        $students = Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');

        $grades = $generation->evaluation
            ? Grade::query()->where('evaluation_id', $generation->evaluation->id)->get()->keyBy('student_id')
            : collect();

        return collect($generation->groups)
            ->map(function (array $studentIds, int $index) use ($students, $grades) {
                $groupStudents = collect($studentIds)
                    ->map(fn (int $id) => $students->get($id))
                    ->filter()
                    ->values();

                $sexCounts = ['f' => 0, 'm' => 0, 'other' => 0];
                foreach ($groupStudents as $student) {
                    if (isset($sexCounts[$student->sex])) {
                        $sexCounts[$student->sex]++;
                    }
                }

                $score = $groupStudents->isNotEmpty()
                    ? $grades->get($groupStudents->first()->id)?->score
                    : null;

                return [
                    'name' => 'Groupe '.($index + 1),
                    'students' => $groupStudents,
                    'sexCounts' => $sexCounts,
                    'score' => $score !== null ? (float) $score : null,
                ];
            })
            ->values()
            ->all();
    }
}
