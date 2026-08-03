<?php

use App\Filament\Pages\SeatingChart;
use App\Models\SchoolClass;
use App\Models\SeatingPlan;
use App\Models\SeatingPlanApplication;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

it('creates a default plan and a default application for a class with none', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)->set('schoolClassId', $class->id);

    $plan = SeatingPlan::query()->first();

    expect($plan)->not->toBeNull()->and($plan->name)->toBe('Plan de classe');

    $application = SeatingPlanApplication::query()
        ->where('seating_plan_id', $plan->id)
        ->where('school_class_id', $class->id)
        ->first();

    expect($application)->not->toBeNull()
        ->and($component->get('applicationId'))->toBe($application->id);
});

it('creates additional uniquely named plans for the same teacher', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('createPlan')
        ->call('createPlan');

    $names = SeatingPlan::query()->orderBy('id')->pluck('name');

    expect($names->all())->toBe(['Plan de classe', 'Plan 1', 'Plan 2']);
});

it('reuses the same plan across two different classes, each with its own independent application', function () {
    $teacher = User::factory()->create();
    $classA = SchoolClass::factory()->for($teacher)->create();
    $classB = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($classA, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($classB, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $classA->id)
        ->call('addDesk', 0, 0);

    $planId = $component->get('planId');
    $desk = SeatingPlanDesk::query()->where('seating_plan_id', $planId)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $desk->id, 0);

    $applicationForA = $component->get('applicationId');

    // Switching class keeps the same base plan (same desks, same room) but
    // resolves — and lets the teacher seat — a wholly separate application.
    $component->set('schoolClassId', $classB->id);

    expect($component->get('planId'))->toBe($planId)
        ->and($component->get('applicationId'))->not->toBe($applicationForA);

    $component
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $desk->id, 0);

    $applicationForB = $component->get('applicationId');

    expect(SeatingPlanSeat::query()->where('seating_plan_application_id', $applicationForA)->first()->student_id)->toBe($studentA->id)
        ->and(SeatingPlanSeat::query()->where('seating_plan_application_id', $applicationForB)->first()->student_id)->toBe($studentB->id)
        ->and(SeatingPlanDesk::query()->where('seating_plan_id', $planId)->count())->toBe(1);
});

it('places a desk on the grid and assigns a student to it by clicking', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('selectStudent', $student->id);

    $desk = SeatingPlanDesk::query()->where('position_row', 0)->where('position_col', 0)->first();

    expect($desk)->not->toBeNull()->and($desk->capacity)->toBe(2);

    $component->call('seatClicked', $desk->id, 0);

    $seat = SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 0)->first();

    expect($seat->student_id)->toBe($student->id);
});

it('moves a seated student to an empty seat, vacating the old one', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1);

    $deskA = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $deskB = SeatingPlanDesk::query()->where('position_col', 1)->first();

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $deskA->id, 0)
        ->call('seatClicked', $deskA->id, 0)
        ->call('seatClicked', $deskB->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $deskA->id)->exists())->toBeFalse()
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $deskB->id)->first()->student_id)->toBe($student->id);
});

it('swaps two seated students when placing one onto an occupied seat', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1);

    $deskA = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $deskB = SeatingPlanDesk::query()->where('position_col', 1)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $deskA->id, 0)
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $deskB->id, 0)
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $deskB->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $deskA->id)->first()->student_id)->toBe($studentB->id)
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $deskB->id)->first()->student_id)->toBe($studentA->id);
});

it('bumps the occupant to unassigned when an unassigned student takes their seat', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $desk->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->first()->student_id)->toBe($studentB->id)
        ->and(SeatingPlanSeat::query()->where('student_id', $studentA->id)->exists())->toBeFalse();
});

it('removes a desk and frees its seated students', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('removeDesk', $desk->id);

    expect(SeatingPlanDesk::query()->find($desk->id))->toBeNull()
        ->and(SeatingPlanSeat::query()->where('student_id', $student->id)->exists())->toBeFalse();
});

it('drops the overflow seat when a desk shrinks from 3 to 2 seats', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->set('newDeskCapacity', 3)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    foreach ($students as $index => $student) {
        $component->call('selectStudent', $student->id)->call('seatClicked', $desk->id, $index);
    }

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->count())->toBe(3);

    $component->call('toggleDeskCapacity', $desk->id);

    expect(SeatingPlanDesk::query()->find($desk->id)->capacity)->toBe(2)
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->count())->toBe(2)
        ->and(SeatingPlanSeat::query()->where('student_id', $students->last()->id)->exists())->toBeFalse();
});

it('adds a row and a column, then removes them again', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)->set('schoolClassId', $class->id);

    // Assert on the raw gridRows/gridCols properties rather than the
    // computed gridSize: Livewire's testing harness memoizes computed
    // get*Property() results on the shared component instance across
    // ->call()s within one test, so a second read can return a stale value
    // even though gridRows/gridCols themselves are always correct (verified
    // against a real request cycle via tinker).
    $baselineRows = $component->get('gridRows');
    $baselineCols = $component->get('gridCols');

    $component->call('addRow')->call('addColumn');

    expect($component->get('gridRows'))->toBe($baselineRows + 1)
        ->and($component->get('gridCols'))->toBe($baselineCols + 1);

    $component->call('removeRow')->call('removeColumn');

    expect($component->get('gridRows'))->toBe($baselineRows)
        ->and($component->get('gridCols'))->toBe($baselineCols);
});

it('removing a row deletes the desks that sat in it', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 1, 0);

    expect(SeatingPlanDesk::query()->count())->toBe(2);

    // The grid is at least 3 rows tall by default, so row 2 (the last one) is
    // empty — removing it should shrink the grid without touching desks.
    $component->call('removeRow');

    expect(SeatingPlanDesk::query()->count())->toBe(2);

    // Keep removing until the desk at row 1 is the last row and gets swept.
    $component->call('removeRow');

    expect(SeatingPlanDesk::query()->where('position_row', 1)->exists())->toBeFalse()
        ->and(SeatingPlanDesk::query()->where('position_row', 0)->exists())->toBeTrue();
});

it('duplicates a plan with its desks but no seat assignments', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('duplicatePlan');

    $copy = SeatingPlan::query()->where('name', 'Plan de classe (copie)')->first();

    expect($copy)->not->toBeNull()
        ->and($component->get('planId'))->toBe($copy->id);

    $copiedDesk = SeatingPlanDesk::query()->where('seating_plan_id', $copy->id)->first();
    $copiedApplication = SeatingPlanApplication::query()->where('seating_plan_id', $copy->id)->first();

    expect($copiedDesk)->not->toBeNull()
        ->and($copiedApplication)->not->toBeNull()
        ->and(SeatingPlanSeat::query()->where('seating_plan_application_id', $copiedApplication->id)->exists())->toBeFalse();
});

it('rejects renaming a plan to a name already used by the same teacher', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('createPlan');

    $component->call('renamePlan', 'Plan de classe');

    expect(SeatingPlan::query()->where('name', 'Plan 1')->exists())->toBeTrue();
});

it('deletes a plan and falls back to a fresh default plan', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)->set('schoolClassId', $class->id);
    $planId = $component->get('planId');

    $component->call('deletePlan');

    expect(SeatingPlan::query()->find($planId))->toBeNull()
        ->and(SeatingPlan::query()->count())->toBe(1);
});

it('randomizes seating up to the available desk capacity', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('randomize');

    $seatedIds = SeatingPlanSeat::query()->pluck('student_id');

    expect($seatedIds)->toHaveCount(2)
        ->and($seatedIds->unique())->toHaveCount(2)
        ->and($component->get('unassignedStudents'))->toHaveCount(1);
});

it('clears all seat assignments', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('clearSeats');

    expect(SeatingPlanSeat::query()->count())->toBe(0);
});

it("prevents a teacher from updating or deleting another teacher's seating plan", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $plan = SeatingPlan::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $plan))->toBeFalse()
        ->and($teacher->can('delete', $plan))->toBeFalse();
});

it("prevents a teacher from updating or deleting another teacher's seating plan application", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $otherPlan = SeatingPlan::factory()->for($otherTeacher)->create();
    $application = SeatingPlanApplication::factory()->for($otherTeacher)->for($otherPlan, 'seatingPlan')->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $application))->toBeFalse()
        ->and($teacher->can('delete', $application))->toBeFalse();
});

it('seats a next-to pair at the same desk when randomizing', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $others = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(2)->create();

    $studentA->seatingNextTo()->sync([$studentB->id]);

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1)
        ->call('randomize');

    $seatA = SeatingPlanSeat::query()->where('student_id', $studentA->id)->first();
    $seatB = SeatingPlanSeat::query()->where('student_id', $studentB->id)->first();

    expect($seatA->seating_plan_desk_id)->toBe($seatB->seating_plan_desk_id);
});

it('respects 1-based allowed rows when randomizing', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'seating_allowed_rows' => ['1'],
    ]);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 1, 0)
        ->call('addDesk', 2, 0)
        ->call('randomize');

    $seat = SeatingPlanSeat::query()->where('student_id', $student->id)->first();
    $desk = SeatingPlanDesk::query()->find($seat->seating_plan_desk_id);

    // "rang 1" from the teacher's form is the 0-indexed grid row 0.
    expect($desk->position_row)->toBe(0);
});

it('respects 1-based allowed columns using the absolute seat position, not the desk index', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'seating_allowed_columns' => ['2'],
    ]);
    Student::factory()->for($teacher)->for($class, 'schoolClass')->count(3)->create();

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1)
        ->call('addDesk', 0, 2)
        ->call('randomize');

    $seat = SeatingPlanSeat::query()->where('student_id', $student->id)->first();
    $desk = SeatingPlanDesk::query()->find($seat->seating_plan_desk_id);

    // Each desk seats 2, so "colonne 2" (1-based, absolute seat index 1) is
    // the second seat of the *first* desk cluster, not the second desk.
    expect($desk->position_col)->toBe(0)
        ->and($seat->seat_index)->toBe(1);
});

it('keeps a not-next-to pair off the same desk when randomizing', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $studentA->seatingNotNextTo()->sync([$studentB->id]);

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1)
        ->call('randomize');

    $seatA = SeatingPlanSeat::query()->where('student_id', $studentA->id)->first();
    $seatB = SeatingPlanSeat::query()->where('student_id', $studentB->id)->first();

    expect($seatA->seating_plan_desk_id)->not->toBe($seatB->seating_plan_desk_id);
});

it('only pulls seating pair constraints between students within the randomized pool', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $otherClass = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $outsider = Student::factory()->for($teacher)->for($otherClass, 'schoolClass')->create();

    // A stray pair referencing a student outside this class's pool must not
    // break constraint gathering (whereIn on both sides excludes it).
    $student->seatingNextTo()->sync([$outsider->id]);

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('randomize');

    expect(SeatingPlanSeat::query()->where('student_id', $student->id)->exists())->toBeTrue();
});

it('flags a manually seated next-to pair placed at different desks as a violation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $studentA->seatingNextTo()->sync([$studentB->id]);

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1);

    $deskA = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $deskB = SeatingPlanDesk::query()->where('position_col', 1)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $deskA->id, 0)
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $deskB->id, 0);

    $violations = $component->get('violations');

    expect($violations)->toHaveKey($studentA->id)
        ->and($violations)->toHaveKey($studentB->id);
});

it('reports no violations when a next-to pair is seated at the same desk', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $studentA->seatingNextTo()->sync([$studentB->id]);

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $desk->id, 1);

    expect($component->get('violations'))->toBe([]);
});

it('flags a manually seated not-next-to pair placed at the same desk as a violation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $studentA->seatingNotNextTo()->sync([$studentB->id]);

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $desk->id, 1);

    expect($component->get('violations'))->toHaveKey($studentA->id)
        ->and($component->get('violations'))->toHaveKey($studentB->id);
});

it('flags a student seated outside their allowed rows or columns as a violation', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'seating_allowed_rows' => ['2'],
        'seating_allowed_columns' => ['2'],
    ]);

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_row', 0)->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $desk->id, 0);

    $violations = $component->get('violations');

    expect($violations[$student->id])->toContain('Rang non autorisé')
        ->and($violations[$student->id])->toContain('Colonne non autorisée');
});

it("keeps the seating chart's student pool isolated from another teacher's class", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    Student::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    $this->actingAs($teacher);

    $students = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->get('students');

    expect($students)->toHaveCount(1)
        ->and($students->first()->id)->toBe($student->id);
});

it('adds a blocked slot that occupies space but is never assignable', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->set('newCellType', 'blocked')
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_row', 0)->where('position_col', 0)->first();

    expect($desk)->not->toBeNull()
        ->and($desk->is_blocked)->toBeTrue();

    $component->call('selectStudent', Student::first()->id)->call('seatClicked', $desk->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->exists())->toBeFalse();
});

it('excludes a blocked slot from randomization but still counts it toward column numbering', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create([
        'seating_allowed_columns' => ['3'],
    ]);

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->set('newCellType', 'blocked')
        ->set('newDeskCapacity', 2)
        ->call('addDesk', 0, 0);

    $component
        ->set('newCellType', 'desk')
        ->call('addDesk', 0, 1)
        ->call('randomize');

    $desk = SeatingPlanDesk::query()->where('position_col', 1)->first();
    $seat = SeatingPlanSeat::query()->where('student_id', $student->id)->first();

    // The blocked slot at column 0 occupies absolute columns 1-2, so the
    // single desk at grid column 1 starts at absolute column 3 (1-based).
    expect($seat->seating_plan_desk_id)->toBe($desk->id)
        ->and($seat->seat_index)->toBe(0);
});

it('locks a manually placed student and keeps them there when randomizing the rest', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1);

    $deskA = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $deskA->id, 0)
        ->call('toggleSeatLock', $deskA->id, 0)
        ->call('randomize');

    $seat = SeatingPlanSeat::query()->where('student_id', $studentA->id)->first();

    expect($seat->seating_plan_desk_id)->toBe($deskA->id)
        ->and($seat->is_locked)->toBeTrue();
});

it('does not let a locked seat be clicked away or overwritten manually', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $studentA = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    $studentB = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1);

    $deskA = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $deskB = SeatingPlanDesk::query()->where('position_col', 1)->first();

    $component
        ->call('selectStudent', $studentA->id)
        ->call('seatClicked', $deskA->id, 0)
        ->call('toggleSeatLock', $deskA->id, 0)
        ->call('selectStudent', $studentB->id)
        ->call('seatClicked', $deskA->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $deskA->id)->first()->student_id)->toBe($studentA->id)
        ->and(SeatingPlanSeat::query()->where('student_id', $studentB->id)->exists())->toBeFalse();
});

it('blocks an empty seat so no student can be placed there, and unblocks it again', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component->call('toggleSeatBlock', $desk->id, 0);

    $blocked = SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 0)->first();
    expect($blocked)->not->toBeNull()
        ->and($blocked->is_blocked)->toBeTrue()
        ->and($blocked->student_id)->toBeNull();

    $component->call('selectStudent', $student->id)->call('seatClicked', $desk->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 0)->first()->student_id)->toBeNull();

    $component->call('toggleSeatBlock', $desk->id, 0);

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 0)->exists())->toBeFalse();
});

it('excludes a blocked empty seat from randomization without freeing it to another student', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $students = Student::factory()->for($teacher)->for($class, 'schoolClass')->count(2)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component->call('toggleSeatBlock', $desk->id, 0)->call('randomize');

    $blocked = SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 0)->first();
    $occupied = SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 1)->first();

    expect($blocked->is_blocked)->toBeTrue()
        ->and($blocked->student_id)->toBeNull()
        ->and($occupied)->not->toBeNull()
        ->and($students->pluck('id'))->toContain($occupied->student_id);
});

it('keeps every archived application around instead of deleting it, hidden from the default list', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)->set('schoolClassId', $class->id);
    $originalApplicationId = $component->get('applicationId');

    $component->call('toggleArchiveApplication');

    expect(SeatingPlanApplication::query()->find($originalApplicationId))->not->toBeNull()
        ->and(SeatingPlanApplication::query()->find($originalApplicationId)->is_archived)->toBeTrue();

    // Archiving the active application falls back to a fresh, non-archived one.
    expect($component->get('applicationId'))->not->toBe($originalApplicationId);

    $withArchived = $component->set('showArchivedApplications', true)->get('applications');
    expect($withArchived->pluck('id'))->toContain($originalApplicationId);

    $withoutArchived = $component->set('showArchivedApplications', false)->get('applications');
    expect($withoutArchived->pluck('id'))->not->toContain($originalApplicationId);
});

it('creates a new dated application of the same plan, starting from a copy of the current seating', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $originalApplicationId = $component->get('applicationId');
    $originalPlanId = $component->get('planId');

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('createApplication')
        ->call('updateEffectiveDate', '2026-09-15');

    $newApplicationId = $component->get('applicationId');

    expect($newApplicationId)->not->toBe($originalApplicationId)
        ->and($component->get('planId'))->toBe($originalPlanId)
        ->and(SeatingPlanApplication::query()->count())->toBe(2)
        ->and(SeatingPlanSeat::query()->where('seating_plan_application_id', $newApplicationId)->where('student_id', $student->id)->exists())->toBeTrue();

    // The two applications are independent from here on: clearing the new
    // one must not touch the original's seats.
    $component->call('clearSeats');

    expect(SeatingPlanSeat::query()->where('seating_plan_application_id', $originalApplicationId)->where('student_id', $student->id)->exists())->toBeTrue()
        ->and(SeatingPlanSeat::query()->where('seating_plan_application_id', $newApplicationId)->exists())->toBeFalse();
});

it('sets an effective date on the current application', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('updateEffectiveDate', '2026-09-01');

    $applicationId = $component->get('applicationId');

    expect(SeatingPlanApplication::query()->find($applicationId)->effective_date->format('Y-m-d'))->toBe('2026-09-01');
});

it('sets the teacher desk position on the plan, shared by every application', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('setTeacherDeskPosition', 'right');

    $planId = $component->get('planId');

    expect(SeatingPlan::query()->find($planId)->teacher_desk_position)->toBe('right');
});

it('avoids reseating a student where they sat in a previous application of the same plan', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0)
        ->call('addDesk', 0, 1);

    $deskA = SeatingPlanDesk::query()->where('position_col', 0)->first();

    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $deskA->id, 0)
        ->call('createApplication');

    // The new application starts as a copy seated exactly like the original;
    // clear it and re-randomize with the "avoid repeat seats" criterion active.
    $component
        ->call('clearSeats')
        ->set('avoidRepeatSeats', true)
        ->call('randomize');

    $newApplicationId = $component->get('applicationId');
    $seat = SeatingPlanSeat::query()->where('seating_plan_application_id', $newApplicationId)->where('student_id', $student->id)->first();

    // "0-0" is the position the student occupied in the original application
    // — the new application's own desk at that same position should now be
    // avoided.
    expect($seat->seating_plan_desk_id)->not->toBe($deskA->id);
});

it('does not let a different plan\'s seating history influence "avoid repeat seat"', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();
    $student = Student::factory()->for($teacher)->for($class, 'schoolClass')->create();
    Student::factory()->for($teacher)->for($class, 'schoolClass')->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();

    // Seat the student at desk 0-0 in the first plan, then switch to a
    // brand new, unrelated plan with a desk at that very same grid position
    // — and a second desk elsewhere, so the assigner actually has a choice.
    $component
        ->call('selectStudent', $student->id)
        ->call('seatClicked', $desk->id, 0)
        ->call('createPlan')
        ->call('addDesk', 0, 0)
        ->call('addDesk', 5, 5);

    // Give the student a positive (weight 50) preference for row 0. This
    // turns "does the assigner avoid the row-0 desk" into a clean cost
    // comparison instead of a coin flip between two otherwise
    // identically-costed desks: if the (buggy) cross-plan repeat-seat
    // penalty (weight 60) applied to row 0, it would outweigh this
    // preference and still push the student to row 5; correctly scoped to
    // this plan alone, there's no penalty on row 0 at all, and the
    // preference wins outright.
    $student->update(['seating_allowed_rows' => ['1']]);

    $component->set('avoidRepeatSeats', true)->call('randomize');

    $sameCoordDesk = SeatingPlanDesk::query()->where('seating_plan_id', $component->get('planId'))->where('position_row', 0)->where('position_col', 0)->first();
    $seat = SeatingPlanSeat::query()->where('student_id', $student->id)->latest('id')->first();

    // A different plan's desk at the same row/col is not the "same seat" —
    // history from an unrelated plan must never veto it.
    expect($seat->seating_plan_desk_id)->toBe($sameCoordDesk->id);
});
