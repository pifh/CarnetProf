<?php

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\GradeCalculator;

function makeGrade(Student $student, Evaluation $evaluation, ?float $score, string $status = 'graded'): Grade
{
    $grade = new Grade([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'score' => $score,
        'status' => $status,
    ]);
    $grade->user_id = $student->user_id;
    $grade->save();

    return $grade;
}

it('normalizes scores to /20 and weights them by coefficient', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    // 8/10 normalized to 16/20, coefficient 1
    $evalA = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create([
        'max_score' => 10, 'coefficient' => 1,
    ]);
    makeGrade($student, $evalA, 8);

    // 18/20, coefficient 2
    $evalB = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create([
        'max_score' => 20, 'coefficient' => 2,
    ]);
    makeGrade($student, $evalB, 18);

    // weighted average = (16*1 + 18*2) / (1+2) = 52/3 = 17.33
    $average = app(GradeCalculator::class)->studentAverage($student, $class, $term);

    expect($average)->toBe(17.33);
});

it('excludes absent, exempted, to_retake and not_graded statuses from the average', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $graded = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    makeGrade($student, $graded, 12);

    $absentEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create();
    makeGrade($student, $absentEval, null, 'absent');

    $exemptedEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create();
    makeGrade($student, $exemptedEval, null, 'exempted');

    $pendingEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create();
    makeGrade($student, $pendingEval, null, 'not_graded');

    $average = app(GradeCalculator::class)->studentAverage($student, $class, $term);

    expect($average)->toBe(12.0);
});

it('returns null when a student has no graded evaluation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    expect(app(GradeCalculator::class)->studentAverage($student, $class))->toBeNull();
});

it('scopes the average to a single term or falls back to the whole year', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $termOne = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1', 'position' => 1]);
    $termTwo = Term::factory()->for($teacher)->create(['label' => 'Trimestre 2', 'position' => 2]);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $evalT1 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($termOne)->create(['max_score' => 20]);
    makeGrade($student, $evalT1, 10);

    $evalT2 = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($termTwo)->create(['max_score' => 20]);
    makeGrade($student, $evalT2, 20);

    $calculator = app(GradeCalculator::class);

    expect($calculator->studentAverage($student, $class, $termOne))->toBe(10.0)
        ->and($calculator->studentAverage($student, $class, $termTwo))->toBe(20.0)
        ->and($calculator->studentAverage($student, $class))->toBe(15.0);
});

it("includes a période's grades in its parent trimestre's average, while the période itself stays isolated", function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $trimestre = Term::factory()->for($teacher)->create(['label' => 'Trimestre 1', 'position' => 1]);
    $periode = Term::factory()->for($teacher)->create(['label' => 'Période 1', 'position' => 1, 'parent_id' => $trimestre->id]);
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $evalTrimestre = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($trimestre)->create(['max_score' => 20]);
    makeGrade($student, $evalTrimestre, 10);

    $evalPeriode = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($periode)->create(['max_score' => 20]);
    makeGrade($student, $evalPeriode, 20);

    $calculator = app(GradeCalculator::class);

    expect($calculator->studentAverage($student, $class, $periode))->toBe(20.0)
        ->and($calculator->studentAverage($student, $class, $trimestre))->toBe(15.0);
});

it('averages every active student to compute the class average, ignoring students with no grade', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();

    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create(); // no grade at all
    Student::factory()->for($teacher)->for($class, 'schoolClass')->archived()->create();

    $eval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);
    makeGrade($studentA, $eval, 10);
    makeGrade($studentB, $eval, 20);

    expect(app(GradeCalculator::class)->classAverage($class, $term))->toBe(15.0);
});

it('scopes averages per subject when a class is shared between several subjects', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $maths = Subject::factory()->for($teacher)->create(['name' => 'Mathématiques']);
    $informatique = Subject::factory()->for($teacher)->create(['name' => 'Informatique']);
    $class->subjects()->attach([$maths->id, $informatique->id]);
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $mathsEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($maths)->create(['max_score' => 20]);
    makeGrade($student, $mathsEval, 8);

    $infoEval = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->for($informatique)->create(['max_score' => 20]);
    makeGrade($student, $infoEval, 16);

    $calculator = app(GradeCalculator::class);

    expect($calculator->studentAverage($student, $class, $term, $maths))->toBe(8.0)
        ->and($calculator->studentAverage($student, $class, $term, $informatique))->toBe(16.0)
        ->and($calculator->studentAverage($student, $class, $term))->toBe(12.0);
});
