<?php

use App\Models\DisciplineEntry;
use App\Models\DisciplineReset;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\DisciplineTracker;
use Illuminate\Support\Carbon;

it("logs an entry stamped with today's date and the acting teacher", function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 15));
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $entry = app(DisciplineTracker::class)->logEntry($student, 'oubli_materiel');

    expect($entry->student_id)->toBe($student->id)
        ->and($entry->category)->toBe('oubli_materiel')
        ->and($entry->occurred_at->format('Y-m-d'))->toBe('2026-09-15')
        ->and($entry->user_id)->toBe($teacher->id);

    Carbon::setTestNow();
});

it('counts total and trip identically before any reset', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($student, 'oubli_materiel');
    $tracker->logEntry($student, 'oubli_materiel');

    $counts = $tracker->countsForStudent($student, 'oubli_materiel');

    expect($counts)->toBe(['total' => 2, 'trip' => 2]);
});

it('resets the trip counter without touching the lifetime total', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($student, 'oubli_materiel');
    $tracker->logEntry($student, 'oubli_materiel');

    $tracker->resetStudent($student, 'oubli_materiel');
    $counts = $tracker->countsForStudent($student, 'oubli_materiel');

    expect($counts)->toBe(['total' => 2, 'trip' => 0]);

    $tracker->logEntry($student, 'oubli_materiel');
    $counts = $tracker->countsForStudent($student, 'oubli_materiel');

    expect($counts)->toBe(['total' => 3, 'trip' => 1]);
});

it('only resets the category asked for, leaving others untouched', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($student, 'oubli_materiel');
    $tracker->logEntry($student, 'bavardage');

    $tracker->resetStudent($student, 'oubli_materiel');

    expect($tracker->countsForStudent($student, 'oubli_materiel'))->toBe(['total' => 1, 'trip' => 0])
        ->and($tracker->countsForStudent($student, 'bavardage'))->toBe(['total' => 1, 'trip' => 1]);
});

it('resets a category for every active student in the class, leaving other classes alone', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($teacher)->create();

    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $archivedStudent = Student::factory()->for($teacher)->for($class, 'schoolClass')->create(['is_archived' => true]);
    $studentElsewhere = Student::factory()->for($teacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);
    $tracker = app(DisciplineTracker::class);

    foreach ([$studentA, $studentB, $archivedStudent, $studentElsewhere] as $student) {
        $tracker->logEntry($student, 'bavardage');
    }

    $tracker->resetClass($class, 'bavardage');

    expect($tracker->countsForStudent($studentA, 'bavardage')['trip'])->toBe(0)
        ->and($tracker->countsForStudent($studentB, 'bavardage')['trip'])->toBe(0)
        ->and($tracker->countsForStudent($archivedStudent, 'bavardage')['trip'])->toBe(1)
        ->and($tracker->countsForStudent($studentElsewhere, 'bavardage')['trip'])->toBe(1);
});

it('computes counts for a whole class in one pass, keyed by student and category', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $this->actingAs($teacher);

    $tracker = app(DisciplineTracker::class);
    $tracker->logEntry($studentA, 'oubli_materiel');
    $tracker->logEntry($studentA, 'oubli_materiel');
    $tracker->logEntry($studentB, 'discipline');

    $counts = $tracker->countsForClass($class);

    expect($counts->get($studentA->id.'|oubli_materiel'))->toBe(['total' => 2, 'trip' => 2])
        ->and($counts->get($studentB->id.'|discipline'))->toBe(['total' => 1, 'trip' => 1])
        ->and($counts->has($studentA->id.'|bavardage'))->toBeFalse();
});

it('scopes entries and resets to the acting teacher only', function () {
    $teacherA = User::factory()->create();
    $teacherB = User::factory()->create();
    $classA = SchoolClass::factory()->for($teacherA)->create();
    $studentA = Student::factory()->for($teacherA)->for($classA, 'schoolClass')->create();

    $this->actingAs($teacherA);
    $entry = app(DisciplineTracker::class)->logEntry($studentA, 'oubli_materiel');
    app(DisciplineTracker::class)->resetStudent($studentA, 'oubli_materiel');

    $this->actingAs($teacherB);

    expect(DisciplineEntry::query()->find($entry->id))->toBeNull()
        ->and(DisciplineReset::query()->where('student_id', $studentA->id)->exists())->toBeFalse();
});
