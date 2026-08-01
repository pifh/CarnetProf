<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

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
        $grades = Grade::query()
            ->where('student_id', $student->id)
            ->where('status', 'graded')
            ->whereNotNull('score')
            ->whereHas('evaluation', function ($query) use ($schoolClass, $term, $subject) {
                $query->where('school_class_id', $schoolClass->id);

                if ($term) {
                    $query->where('term_id', $term->id);
                }

                if ($subject) {
                    $query->where('subject_id', $subject->id);
                }
            })
            ->with('evaluation')
            ->get();

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
     * Average of every active student's average in the class (excluding
     * students who have no graded evaluation yet).
     */
    public function classAverage(SchoolClass $schoolClass, ?Term $term = null, ?Subject $subject = null): ?float
    {
        $averages = $schoolClass->students()
            ->where('is_archived', false)
            ->get()
            ->map(fn (Student $student) => $this->studentAverage($student, $schoolClass, $term, $subject))
            ->filter(fn (?float $average) => $average !== null);

        return $averages->isNotEmpty() ? round($averages->avg(), 2) : null;
    }
}
