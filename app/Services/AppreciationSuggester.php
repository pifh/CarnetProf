<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;

class AppreciationSuggester
{
    public function __construct(private readonly GradeCalculator $gradeCalculator) {}

    /**
     * Suggest a French appreciation text based on the student's average for
     * the given term (or the full year when $term is null), adjusted for the
     * trend versus the previous term. Returns null when there is no grade to
     * base a suggestion on.
     */
    public function suggest(Student $student, SchoolClass $schoolClass, ?Term $term = null, ?Subject $subject = null): ?string
    {
        $average = $this->gradeCalculator->studentAverage($student, $schoolClass, $term, $subject);

        if ($average === null) {
            return null;
        }

        $sentence = $this->sentenceForAverage($average);
        $trend = $this->trendSentence($student, $schoolClass, $term, $average, $subject);

        return trim("{$sentence} {$trend}");
    }

    private function sentenceForAverage(float $average): string
    {
        return match (true) {
            $average >= 16 => 'Excellent trimestre, résultats remarquables. Continuez ainsi.',
            $average >= 14 => 'Bon trimestre, résultats satisfaisants dans l\'ensemble.',
            $average >= 12 => 'Trimestre correct, des efforts à poursuivre pour progresser encore.',
            $average >= 10 => 'Résultats justes mais moyens. Un travail plus régulier est attendu.',
            $average >= 8 => 'Résultats fragiles, un travail plus soutenu est indispensable.',
            default => 'Résultats insuffisants. Une remobilisation rapide est nécessaire.',
        };
    }

    private function trendSentence(Student $student, SchoolClass $schoolClass, ?Term $term, float $average, ?Subject $subject = null): string
    {
        if (! $term) {
            return '';
        }

        $previousTerm = Term::query()
            ->where('user_id', $term->user_id)
            ->where('school_year', $term->school_year)
            ->where('parent_id', $term->parent_id)
            ->where('position', '<', $term->position)
            ->orderByDesc('position')
            ->first();

        if (! $previousTerm) {
            return '';
        }

        $previousAverage = $this->gradeCalculator->studentAverage($student, $schoolClass, $previousTerm, $subject);

        if ($previousAverage === null) {
            return '';
        }

        $difference = round($average - $previousAverage, 2);

        return match (true) {
            $difference >= 1 => 'Progression notable par rapport au trimestre précédent.',
            $difference <= -1 => 'Baisse à surveiller par rapport au trimestre précédent.',
            default => 'Résultats stables par rapport au trimestre précédent.',
        };
    }
}
