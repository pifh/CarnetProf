<?php

namespace App\Filament\Pages;

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\GradeCalculator;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class GradeTracking extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Suivi des notes';

    protected static ?string $title = 'Suivi des notes';

    protected static ?int $navigationSort = 28;

    protected string $view = 'filament.pages.grade-tracking';

    public ?int $schoolClassId = null;

    public ?int $subjectId = null;

    public ?int $termId = null;

    public ?int $selectedStudentId = null;

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
        $this->selectedStudentId = null;
        $this->syncSubjectId();
    }

    private function syncSubjectId(): void
    {
        $subjectIds = $this->getSubjectsProperty()->pluck('id');

        if (! $subjectIds->contains($this->subjectId)) {
            $this->subjectId = $subjectIds->first();
        }
    }

    public function toggleStudent(int $studentId): void
    {
        $this->selectedStudentId = $this->selectedStudentId === $studentId ? null : $studentId;
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

    private function getSelectedSchoolClass(): ?SchoolClass
    {
        return $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
    }

    private function getSelectedTerm(): ?Term
    {
        return $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
    }

    private function getSelectedSubject(): ?Subject
    {
        return $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
    }

    /**
     * The evaluations to show as columns when a single term is selected —
     * empty when viewing the whole year, since that view only shows
     * averages instead of every individual grade.
     *
     * @return Collection<int, Evaluation>
     */
    public function getTermEvaluationsProperty(): Collection
    {
        $term = $this->getSelectedTerm();

        if (! $term || ! $this->schoolClassId) {
            return collect();
        }

        $subject = $this->getSelectedSubject();

        return Evaluation::query()
            ->where('school_class_id', $this->schoolClassId)
            ->where('term_id', $term->id)
            ->when($subject, fn ($query) => $query->where('subject_id', $subject->id))
            ->orderBy('exam_date')
            ->get();
    }

    /**
     * @param  Collection<int, Evaluation>  $evaluations
     * @return Collection<int, Collection<int, Grade>>
     */
    private function loadGradesForEvaluations(Collection $evaluations): Collection
    {
        return Grade::query()
            ->whereIn('evaluation_id', $evaluations->pluck('id'))
            ->get()
            ->groupBy('evaluation_id');
    }

    /**
     * One row per active student. When a single term is selected, each
     * evaluation of that term becomes a column (every grade is visible).
     * When viewing the whole year, only the per-term and annual averages
     * are shown, to keep the table readable.
     *
     * @return Collection<int, array{student: Student, grades?: array<int, ?Grade>, termAverage?: ?float, termAverages?: array<int, ?float>, annualAverage?: ?float}>
     */
    public function getSummaryRowsProperty(): Collection
    {
        $schoolClass = $this->getSelectedSchoolClass();

        if (! $schoolClass) {
            return collect();
        }

        $subject = $this->getSelectedSubject();
        $calculator = app(GradeCalculator::class);
        $students = $schoolClass->allStudents()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $term = $this->getSelectedTerm();

        if ($term) {
            $evaluations = $this->getTermEvaluationsProperty();
            $gradesByEvaluation = $this->loadGradesForEvaluations($evaluations);

            return $students->map(fn (Student $student) => [
                'student' => $student,
                'grades' => $evaluations->mapWithKeys(fn (Evaluation $evaluation) => [
                    $evaluation->id => ($gradesByEvaluation->get($evaluation->id) ?? collect())->firstWhere('student_id', $student->id),
                ])->all(),
                'termAverage' => $calculator->studentAverage($student, $schoolClass, $term, $subject),
            ]);
        }

        $terms = $this->getTermsProperty();

        return $students->map(fn (Student $student) => [
            'student' => $student,
            'termAverages' => $terms->mapWithKeys(fn (Term $term) => [
                $term->id => $calculator->studentAverage($student, $schoolClass, $term, $subject),
            ])->all(),
            'annualAverage' => $calculator->studentAverage($student, $schoolClass, null, $subject),
        ]);
    }

    /**
     * Footer row matching whichever shape getSummaryRowsProperty() is
     * currently using: per-evaluation class averages for a single term, or
     * per-term and annual class averages for the whole year.
     *
     * @return array{evaluationAverages?: array<int, ?float>, termAverage?: ?float, termAverages?: array<int, ?float>, annualAverage?: ?float}
     */
    public function getClassSummaryProperty(): array
    {
        $schoolClass = $this->getSelectedSchoolClass();

        if (! $schoolClass) {
            return [];
        }

        $subject = $this->getSelectedSubject();
        $calculator = app(GradeCalculator::class);
        $term = $this->getSelectedTerm();

        if ($term) {
            $evaluations = $this->getTermEvaluationsProperty();
            $gradesByEvaluation = $this->loadGradesForEvaluations($evaluations);

            return [
                'evaluationAverages' => $evaluations->mapWithKeys(function (Evaluation $evaluation) use ($gradesByEvaluation) {
                    $graded = ($gradesByEvaluation->get($evaluation->id) ?? collect())
                        ->filter(fn (Grade $grade) => $grade->status === 'graded' && $grade->score !== null);

                    if ($graded->isEmpty()) {
                        return [$evaluation->id => null];
                    }

                    $average = $graded->avg(fn (Grade $grade) => (float) $grade->score);

                    return [$evaluation->id => round(($average / (float) $evaluation->max_score) * 20, 2)];
                })->all(),
                'termAverage' => $calculator->classAverage($schoolClass, $term, $subject),
            ];
        }

        $terms = $this->getTermsProperty();

        return [
            'termAverages' => $terms->mapWithKeys(fn (Term $term) => [
                $term->id => $calculator->classAverage($schoolClass, $term, $subject),
            ])->all(),
            'annualAverage' => $calculator->classAverage($schoolClass, null, $subject),
        ];
    }

    /**
     * Every graded-or-not grade for the selected student, scoped to the
     * chosen matière/période filters, oldest first — feeds the progression
     * chart (only the graded ones actually get plotted).
     *
     * @return Collection<int, Grade>
     */
    public function getSelectedStudentGradesProperty(): Collection
    {
        if (! $this->selectedStudentId || ! $this->schoolClassId) {
            return collect();
        }

        $term = $this->getSelectedTerm();
        $subject = $this->getSelectedSubject();

        return Grade::query()
            ->where('student_id', $this->selectedStudentId)
            ->whereHas('evaluation', function ($query) use ($term, $subject) {
                $query->where('school_class_id', $this->schoolClassId);

                if ($term) {
                    $query->where('term_id', $term->id);
                }

                if ($subject) {
                    $query->where('subject_id', $subject->id);
                }
            })
            ->with(['evaluation.subject', 'evaluation.term'])
            ->get()
            ->sortBy(fn (Grade $grade) => $grade->evaluation->exam_date)
            ->values();
    }

    /**
     * Chart points only make sense for actually graded evaluations —
     * absences/exemptions have no score to plot. Coordinates are percentages
     * (0-100) so the blade partial can draw the SVG without doing any math.
     *
     * @return array<int, array{x: float, y: float, label: string, date: ?string, score: float}>
     */
    public function getSelectedStudentChartPointsProperty(): array
    {
        $graded = $this->getSelectedStudentGradesProperty()
            ->filter(fn (Grade $grade) => $grade->status === 'graded' && $grade->score !== null)
            ->values();

        $count = $graded->count();

        if ($count === 0) {
            return [];
        }

        return $graded->map(function (Grade $grade, int $index) use ($count) {
            $normalized = ((float) $grade->score / (float) $grade->evaluation->max_score) * 20;

            return [
                'x' => $count > 1 ? round(($index / ($count - 1)) * 100, 2) : 50.0,
                'y' => round(100 - ($normalized / 20) * 100, 2),
                'label' => $grade->evaluation->title,
                'date' => $grade->evaluation->exam_date?->format('d/m/Y'),
                'score' => round($normalized, 2),
            ];
        })->all();
    }

    public function exportCsv()
    {
        $rows = $this->getSummaryRowsProperty();
        $schoolClass = $this->getSelectedSchoolClass();
        $term = $this->getSelectedTerm();

        $filename = 'suivi-notes-'.str(($schoolClass?->name ?? 'classe').'-'.($term?->label ?? 'annee'))->slug().'.csv';

        if ($term) {
            $evaluations = $this->getTermEvaluationsProperty();

            return Response::streamDownload(function () use ($rows, $evaluations) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, [
                    'Nom',
                    'Prénom',
                    ...$evaluations->pluck('title')->all(),
                    'Moyenne',
                ]);

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row['student']->last_name,
                        $row['student']->first_name,
                        ...$evaluations->map(function (Evaluation $evaluation) use ($row) {
                            $grade = $row['grades'][$evaluation->id] ?? null;

                            return $grade && $grade->status === 'graded' && $grade->score !== null
                                ? number_format($grade->score, 2)
                                : '';
                        })->all(),
                        $row['termAverage'] !== null ? number_format($row['termAverage'], 2) : '',
                    ]);
                }

                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv']);
        }

        $terms = $this->getTermsProperty();

        return Response::streamDownload(function () use ($rows, $terms) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Nom',
                'Prénom',
                ...$terms->pluck('label')->all(),
                'Moyenne annuelle',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student']->last_name,
                    $row['student']->first_name,
                    ...$terms->map(fn (Term $term) => $row['termAverages'][$term->id] !== null
                        ? number_format($row['termAverages'][$term->id], 2)
                        : '')->all(),
                    $row['annualAverage'] !== null ? number_format($row['annualAverage'], 2) : '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
