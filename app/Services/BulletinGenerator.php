<?php

namespace App\Services;

use App\Models\Appreciation;
use App\Models\Evaluation;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BulletinGenerator
{
    public function __construct(private readonly GradeCalculator $gradeCalculator) {}

    /**
     * Assemble everything a printable bulletin needs for one student, in one
     * class, for one term. Pass a subject to scope to a single matière when
     * the class has more than one. Only published appreciations are
     * included — drafts and private teacher notes never leave the app.
     *
     * @return array{
     *     student: Student,
     *     schoolClass: SchoolClass,
     *     subject: ?Subject,
     *     term: Term,
     *     evaluations: Collection<int, array{title: string, exam_date: ?Carbon, score: ?float, max_score: float, coefficient: float, status: string}>,
     *     average: ?float,
     *     classAverage: ?float,
     *     generalAppreciation: ?string,
     *     disciplinaryAppreciation: ?string,
     * }
     */
    public function build(Student $student, SchoolClass $schoolClass, Term $term, ?Subject $subject = null): array
    {
        $evaluations = Evaluation::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('term_id', $term->id)
            ->when($subject, fn ($query) => $query->where('subject_id', $subject->id))
            ->with(['grades' => fn ($query) => $query->where('student_id', $student->id)])
            ->orderBy('exam_date')
            ->get()
            ->map(function (Evaluation $evaluation) {
                $grade = $evaluation->grades->first();

                return [
                    'title' => $evaluation->title,
                    'exam_date' => $evaluation->exam_date,
                    'score' => $grade?->score !== null ? (float) $grade->score : null,
                    'max_score' => (float) $evaluation->max_score,
                    'coefficient' => (float) $evaluation->coefficient,
                    'status' => $grade?->status ?? 'not_graded',
                ];
            });

        $appreciations = Appreciation::query()
            ->where('student_id', $student->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $subject?->id)
            ->where('term_id', $term->id)
            ->where('is_draft', false)
            ->get()
            ->keyBy('type');

        return [
            'student' => $student,
            'schoolClass' => $schoolClass,
            'subject' => $subject,
            'term' => $term,
            'evaluations' => $evaluations,
            'average' => $this->gradeCalculator->studentAverage($student, $schoolClass, $term, $subject),
            'classAverage' => $this->gradeCalculator->classAverage($schoolClass, $term, $subject),
            'generalAppreciation' => $appreciations->get('general')?->content,
            'disciplinaryAppreciation' => $appreciations->get('disciplinary')?->content,
        ];
    }
}
