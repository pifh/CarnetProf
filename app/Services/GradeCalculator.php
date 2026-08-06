<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Collection;

class GradeCalculator
{
    /**
     * Weighted average out of 20 for a student, across every graded evaluation
     * for the given class. Pass a term to scope to a single trimester, or
     * omit it for the annual average. Pass a subject to scope to a single
     * matière when the class has more than one. Returns null if nothing is
     * graded yet.
     */
    public function studentAverage(Student $student, SchoolClass $schoolClass, ?Term $term = null, ?Subject $subject = null): ?float
    {
        $grades = $this->gradesFor($student, $schoolClass, $term, $subject);

        if ($grades->isEmpty()) {
            return null;
        }

        $weightedSum = 0;
        $totalCoefficient = 0;

        foreach ($grades as $grade) {
            $coefficient = (float) $grade->evaluation->coefficient;
            $normalizedScore = ((float) $grade->score / (float) $grade->evaluation->max_score) * 20;

            $weightedSum += $normalizedScore * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return $totalCoefficient > 0 ? round($weightedSum / $totalCoefficient, 2) : null;
    }

    /**
     * Every graded grade (with its evaluation eager-loaded) making up a
     * student's average for the given scope — same filters as
     * studentAverage(), but returned individually instead of reduced to a
     * number. When $term is a trimestre with sub-periods, grades logged
     * against those periods are included too (a period's grades belong to
     * its parent trimestre); a period itself only ever resolves to its own
     * grades.
     *
     * @return Collection<int, Grade>
     */
    public function gradesFor(Student $student, SchoolClass $schoolClass, ?Term $term = null, ?Subject $subject = null): Collection
    {
        return Grade::query()
            ->where('student_id', $student->id)
            ->where('status', 'graded')
            ->whereNotNull('score')
            ->whereHas('evaluation', function ($query) use ($schoolClass, $term, $subject) {
                $query->where('school_class_id', $schoolClass->id);

                if ($term) {
                    $query->whereIn('term_id', $term->aggregationTermIds());
                }

                if ($subject) {
                    $query->where('subject_id', $subject->id);
                }
            })
            ->with('evaluation')
            ->get();
    }

    /**
     * Average of every active student's average in the class (excluding
     * students who have no graded evaluation yet).
     */
    public function classAverage(SchoolClass $schoolClass, ?Term $term = null, ?Subject $subject = null): ?float
    {
        $averages = $schoolClass->allStudents()
            ->where('is_archived', false)
            ->get()
            ->map(fn (Student $student) => $this->studentAverage($student, $schoolClass, $term, $subject))
            ->filter(fn (?float $average) => $average !== null);

        return $averages->isNotEmpty() ? round($averages->avg(), 2) : null;
    }
}
