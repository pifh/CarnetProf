<?php

use App\Filament\Pages\Averages;
use App\Filament\Pages\EvaluationGrades;
use App\Filament\Resources\Evaluations\Pages\ListEvaluations;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Livewire\Livewire;

it('lets a teacher enter a score which flips the grade to graded', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);

    $this->actingAs($teacher);

    Livewire::test(EvaluationGrades::class, ['evaluation' => $evaluation])
        ->call('updateScore', $student->id, '15,5');

    $grade = Grade::query()->where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->first();

    expect((float) $grade->score)->toBe(15.5)
        ->and($grade->status)->toBe('graded')
        ->and($grade->user_id)->toBe($teacher->id);
});

it('caps an entered score to the evaluation\'s max score', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);

    $this->actingAs($teacher);

    Livewire::test(EvaluationGrades::class, ['evaluation' => $evaluation])
        ->call('updateScore', $student->id, '999');

    $grade = Grade::query()->where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->first();

    expect((float) $grade->score)->toBe(20.0);
});

it('sets a status like absent and clears the score', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(EvaluationGrades::class, ['evaluation' => $evaluation])
        ->call('updateScore', $student->id, '10')
        ->call('setStatus', $student->id, 'absent');

    $grade = Grade::query()->where('evaluation_id', $evaluation->id)->where('student_id', $student->id)->first();

    expect($grade->status)->toBe('absent')
        ->and($grade->score)->toBeNull();
});

it("blocks a teacher from opening another teacher's evaluation grade grid", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherTerm = Term::factory()->for($otherTeacher)->create();
    $evaluation = Evaluation::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->for($otherTerm)->create();

    $this->actingAs($teacher);

    Livewire::test(EvaluationGrades::class, ['evaluation' => $evaluation])
        ->assertForbidden();
});

it('computes per-student and class averages on the Averages page', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Aaa']);
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['last_name' => 'Bbb']);
    $evaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create(['max_score' => 20]);

    Grade::factory()->for($teacher)->for($evaluation)->for($studentA, 'student')->create(['score' => 10, 'status' => 'graded']);
    Grade::factory()->for($teacher)->for($evaluation)->for($studentB, 'student')->create(['score' => 20, 'status' => 'graded']);

    $this->actingAs($teacher);

    $component = Livewire::test(Averages::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id);

    $rows = $component->get('rows');

    expect($rows->firstWhere('student.id', $studentA->id)['average'])->toBe(10.0)
        ->and($rows->firstWhere('student.id', $studentB->id)['average'])->toBe(20.0)
        ->and($component->instance()->getClassAverage())->toBe('15.00');
});

it('scopes the Averages page to the selected subject when a class has several', function () {
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

    $component = Livewire::test(Averages::class)
        ->set('schoolClassId', $class->id)
        ->set('termId', $term->id)
        ->set('subjectId', $maths->id);

    expect($component->get('rows')->firstWhere('student.id', $student->id)['average'])->toBe(8.0);

    $component->set('subjectId', $informatique->id);

    expect($component->get('rows')->firstWhere('student.id', $student->id)['average'])->toBe(16.0);
});

it('only lists a teacher\'s own evaluations', function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();

    $class = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $ownEvaluation = Evaluation::factory()->for($teacher)->for($class, 'schoolClass')->for($term)->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherTerm = Term::factory()->for($otherTeacher)->create();
    Evaluation::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->for($otherTerm)->create();

    $this->actingAs($teacher);

    Livewire::test(ListEvaluations::class)
        ->assertCanSeeTableRecords([$ownEvaluation])
        ->assertCountTableRecords(1);
});

it("prevents a teacher from updating or deleting another teacher's evaluation", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherTerm = Term::factory()->for($otherTeacher)->create();
    $evaluation = Evaluation::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->for($otherTerm)->create();

    expect($teacher->can('update', $evaluation))->toBeFalse()
        ->and($teacher->can('delete', $evaluation))->toBeFalse();
});
