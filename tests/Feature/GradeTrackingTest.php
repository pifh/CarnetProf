<?php

use App\Filament\Pages\GradeTracking;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

it('shows per-term and annual averages for every student in the class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term1 = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1', 'position' => 1]);
    $term2 = Term::factory()->for($teacher)->create(['label' => 'Trimestre 2', 'position' => 2]);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $eval1 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term1)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($eval1)->for($student, 'student')->create(['score' => 10, 'status' => 'graded']);

    $eval2 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term2)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($eval2)->for($student, 'student')->create(['score' => 20, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)->set('schoolClassId', $class->id);

    $row = $component->get('summaryRows')->firstWhere('student.id', $student->id);

    expect($row['termAverages'][$term1->id])->toBe(10.0)
        ->and($row['termAverages'][$term2->id])->toBe(20.0)
        ->and($row['annualAverage'])->toBe(15.0);
});

it('computes the class summary as the average of every student average', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $evalA = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($evalA)->for($studentA, 'student')->create(['score' => 10, 'status' => 'graded']);
    Grade::factory()->for($teacher)->for($evalA)->for($studentB, 'student')->create(['score' => 20, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)->set('schoolClassId', $class->id);

    expect($component->get('classSummary')['termAverages'][$term->id])->toBe(15.0)
        ->and($component->get('classSummary')['annualAverage'])->toBe(15.0);
});

it('lists every grade for the selected student including ungraded ones', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $eval1 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['title' => 'Contrôle 1', 'max_score' => 20, 'exam_date' => '2026-01-10']);
    Grade::factory()->for($teacher)->for($eval1)->for($student, 'student')->create(['score' => 14, 'status' => 'graded']);

    $eval2 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['title' => 'Contrôle 2', 'max_score' => 20, 'exam_date' => '2026-02-10']);
    Grade::factory()->for($teacher)->for($eval2)->for($student, 'student')->absent()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleStudent', $student->id);

    $grades = $component->get('selectedStudentGrades');

    expect($grades)->toHaveCount(2)
        ->and($grades->pluck('status')->all())->toBe(['graded', 'absent']);
});

it('only plots graded evaluations on the progression chart, normalized to /20', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $eval1 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 10, 'exam_date' => '2026-01-10']);
    Grade::factory()->for($teacher)->for($eval1)->for($student, 'student')->create(['score' => 5, 'status' => 'graded']);

    $eval2 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20, 'exam_date' => '2026-02-10']);
    Grade::factory()->for($teacher)->for($eval2)->for($student, 'student')->notGraded();

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleStudent', $student->id);

    $points = $component->get('selectedStudentChartPoints');

    expect($points)->toHaveCount(1)
        ->and($points[0]['score'])->toBe(10.0);
});

it('filters the selected student\'s grade list by term', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term1 = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1', 'position' => 1]);
    $term2 = Term::factory()->for($teacher)->create(['label' => 'Trimestre 2', 'position' => 2]);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $eval1 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term1)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($eval1)->for($student, 'student')->create(['score' => 12, 'status' => 'graded']);

    $eval2 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term2)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($eval2)->for($student, 'student')->create(['score' => 18, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->call('toggleStudent', $student->id)
        ->set('termId', $term1->id);

    expect($component->get('selectedStudentGrades'))->toHaveCount(1);
});

it('scopes averages to the selected subject when a class has several', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $class->subjects()->attach([$maths->id, $informatique->id]);
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $mathsEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($maths)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($mathsEval)->for($student, 'student')->create(['score' => 8, 'status' => 'graded']);

    $infoEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($informatique)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($infoEval)->for($student, 'student')->create(['score' => 16, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->set('subjectId', $maths->id);

    expect($component->get('summaryRows')->firstWhere('student.id', $student->id)['annualAverage'])->toBe(8.0);

    $component->set('subjectId', $informatique->id);

    expect($component->get('summaryRows')->firstWhere('student.id', $student->id)['annualAverage'])->toBe(16.0);
});

it('lists every evaluation of the selected term as a grade column, including ungraded ones', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $eval1 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['title' => 'Contrôle 1', 'max_score' => 20, 'exam_date' => '2026-01-10']);
    Grade::factory()->for($teacher)->for($eval1)->for($student, 'student')->create(['score' => 12, 'status' => 'graded']);

    $eval2 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['title' => 'Contrôle 2', 'max_score' => 20, 'exam_date' => '2026-02-10']);
    Grade::factory()->for($teacher)->for($eval2)->for($student, 'student')->absent()->create();

    // A third evaluation with no Grade row at all for this student yet.
    $eval3 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['title' => 'Contrôle 3', 'max_score' => 20, 'exam_date' => '2026-03-10']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id);

    $evaluations = $component->get('termEvaluations');
    expect($evaluations->pluck('title')->all())->toBe(['Contrôle 1', 'Contrôle 2', 'Contrôle 3']);

    $row = $component->get('summaryRows')->firstWhere('student.id', $student->id);

    expect($row['grades'][$eval1->id]->status)->toBe('graded')
        ->and((float) $row['grades'][$eval1->id]->score)->toBe(12.0)
        ->and($row['grades'][$eval2->id]->status)->toBe('absent')
        ->and($row['grades'][$eval3->id])->toBeNull()
        ->and($row['termAverage'])->toBe(12.0);
});

it('computes per-evaluation class averages when a single term is selected', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $eval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    Grade::factory()->for($teacher)->for($eval)->for($studentA, 'student')->create(['score' => 10, 'status' => 'graded']);
    Grade::factory()->for($teacher)->for($eval)->for($studentB, 'student')->create(['score' => 16, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id);

    expect($component->get('classSummary')['evaluationAverages'][$eval->id])->toBe(13.0);
});

it("only shows a teacher's own class in the school class list", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    SchoolClass::factory()->for($teacher)->create(['name' => 'Ma classe']);
    SchoolClass::factory()->for($otherTeacher)->create(['name' => 'Classe d\'un autre']);

    $this->actingAs($teacher);

    $component = Livewire::test(GradeTracking::class);

    expect($component->get('schoolClasses')->pluck('name')->all())->toBe(['Ma classe']);
});
