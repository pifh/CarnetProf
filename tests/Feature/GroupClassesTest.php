<?php

use App\Filament\Resources\SchoolClasses\Pages\EditSchoolClass;
use App\Filament\Resources\SchoolClasses\RelationManagers\GroupClassMembersRelationManager;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\DisciplineTracker;
use App\Services\GradeCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

it("includes a groupe classe's own primary students plus its attached members", function () {
    $teacher = User::factory()->create();
    $realClassA = SchoolClass::factory()->for($teacher)->create();
    $realClassB = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();

    $studentA = Student::factory()->for($teacher)->for($realClassA, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($realClassB, 'schoolClass')->create();
    $unrelatedStudent = Student::factory()->for($teacher)->for($realClassA, 'schoolClass')->create();

    $studentA->groupClasses()->attach($groupClass);
    $studentB->groupClasses()->attach($groupClass);

    expect($groupClass->allStudents()->pluck('id')->sort()->values()->all())
        ->toBe([$studentA->id, $studentB->id])
        ->and($groupClass->allStudents()->pluck('id'))->not->toContain($unrelatedStudent->id);
});

it("does not double-count a student who is both a groupe classe's primary and attached member", function () {
    $teacher = User::factory()->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($groupClass, 'schoolClass')->create();

    $student->groupClasses()->attach($groupClass);

    expect($groupClass->allStudents()->count())->toBe(1);
});

it("keeps a student's grades independent between their real class and a groupe classe", function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $term = Term::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $student->groupClasses()->attach($groupClass);

    $realEvaluation = Evaluation::factory()->for($teacher)->for($realClass, 'schoolClass')->for($term)->create(['max_score' => 20]);
    $groupEvaluation = Evaluation::factory()->for($teacher)->for($groupClass, 'schoolClass')->for($term)->create(['max_score' => 20]);

    Grade::factory()->for($teacher)->for($realEvaluation)->for($student)->create(['score' => 8, 'status' => 'graded']);
    Grade::factory()->for($teacher)->for($groupEvaluation)->for($student)->create(['score' => 16, 'status' => 'graded']);

    $calculator = app(GradeCalculator::class);

    expect($calculator->studentAverage($student, $realClass))->toBe(8.0)
        ->and($calculator->studentAverage($student, $groupClass))->toBe(16.0)
        ->and($calculator->classAverage($groupClass))->toBe(16.0);
});

it("keeps a student's discipline counters independent between their real class and a groupe classe", function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $student->groupClasses()->attach($groupClass);
    Auth::login($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($student, $realClass, 'oubli_materiel');
    $tracker->logEntry($student, $groupClass, 'oubli_materiel');
    $tracker->logEntry($student, $groupClass, 'oubli_materiel');
    $tracker->resetStudent($student, $groupClass, 'oubli_materiel');

    expect($tracker->countsForStudent($student, $realClass, 'oubli_materiel'))->toBe(['total' => 1, 'trip' => 1])
        ->and($tracker->countsForStudent($student, $groupClass, 'oubli_materiel'))->toBe(['total' => 2, 'trip' => 0]);
});

it('does not archive members borrowed from another class when a groupe classe is archived', function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $student->groupClasses()->attach($groupClass);

    // Mirrors the real archive cascade (SchoolClassesTable's "archive" table
    // action): it only ever touches students(), the primary/home relation —
    // a groupe classe's borrowed members aren't in it, so this should be a
    // no-op for $student.
    $groupClass->students()->where('is_archived', false)->update(['is_archived' => true, 'archived_at' => now()]);
    $groupClass->update(['is_archived' => true, 'archived_at' => now()]);

    expect($student->fresh()->is_archived)->toBeFalse()
        ->and($realClass->fresh()->is_archived)->toBeFalse();
});

it('archives a student borrowed by a groupe classe when their real class is archived, without archiving the groupe classe', function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $student->groupClasses()->attach($groupClass);

    $realClass->students()->where('is_archived', false)->update(['is_archived' => true, 'archived_at' => now()]);
    $realClass->update(['is_archived' => true, 'archived_at' => now()]);

    expect($student->fresh()->is_archived)->toBeTrue()
        ->and($groupClass->fresh()->is_archived)->toBeFalse()
        ->and($groupClass->groupClassMembers()->where('student_id', $student->id)->exists())->toBeTrue();
});

it('lets a teacher attach a student from another class to a groupe classe', function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $this->actingAs($teacher);

    Livewire::test(GroupClassMembersRelationManager::class, [
        'ownerRecord' => $groupClass,
        'pageClass' => EditSchoolClass::class,
    ])->callTableAction('attach', data: ['recordId' => $student->id]);

    expect($groupClass->groupClassMembers()->where('student_id', $student->id)->exists())->toBeTrue();
});

it('lets a teacher detach a groupe classe member without affecting their real class', function () {
    $teacher = User::factory()->create();
    $realClass = SchoolClass::factory()->for($teacher)->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($realClass, 'schoolClass')->create();
    $student->groupClasses()->attach($groupClass);
    $this->actingAs($teacher);

    Livewire::test(GroupClassMembersRelationManager::class, [
        'ownerRecord' => $groupClass,
        'pageClass' => EditSchoolClass::class,
    ])->callTableAction('detach', $student);

    expect($groupClass->groupClassMembers()->where('student_id', $student->id)->exists())->toBeFalse()
        ->and($student->fresh()->school_class_id)->toBe($realClass->id);
});

it("only offers the acting teacher's own students in the attach picker", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $groupClass = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherStudent = Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();
    $this->actingAs($teacher);

    Livewire::test(GroupClassMembersRelationManager::class, [
        'ownerRecord' => $groupClass,
        'pageClass' => EditSchoolClass::class,
    ])->callTableAction('attach', data: ['recordId' => $otherStudent->id]);

    expect($groupClass->groupClassMembers()->where('student_id', $otherStudent->id)->exists())->toBeFalse();
});
