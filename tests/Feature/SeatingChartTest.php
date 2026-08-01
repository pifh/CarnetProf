<?php

use App\Filament\Pages\SeatingChart;
use App\Models\SchoolClass;
use App\Models\SeatingPlan;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use App\Models\Student;
use App\Models\User;
use Livewire\Livewire;

it('creates a default plan for a class with none', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    Livewire::test(SeatingChart::class)->set('schoolClassId', $class->id);

    $plan = SeatingPlan::query()->where('school_class_id', $class->id)->first();

    expect($plan)->not->toBeNull()->and($plan->name)->toBe('Plan de classe');
});

it('creates additional uniquely named plans for the same class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('createPlan')
        ->call('createPlan');

    $names = SeatingPlan::query()->where('school_class_id', $class->id)->orderBy('id')->pluck('name');

    expect($names->all())->toBe(['Plan de classe', 'Plan 1', 'Plan 2']);
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

it('duplicates a plan with its desks and seat assignments', function () {
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

    $originalPlanId = SeatingPlan::query()->where('name', 'Plan de classe')->value('id');
    $copy = SeatingPlan::query()->where('name', 'Plan de classe (copie)')->first();

    expect($copy)->not->toBeNull()
        ->and($component->get('planId'))->toBe($copy->id);

    $copiedDesk = SeatingPlanDesk::query()->where('seating_plan_id', $copy->id)->first();

    expect($copiedDesk)->not->toBeNull()
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $copiedDesk->id)->first()->student_id)->toBe($student->id)
        ->and(SeatingPlanSeat::query()->where('seating_plan_id', $originalPlanId)->count())->toBe(1);
});

it('rejects renaming a plan to a name already used in the same class', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)
        ->set('schoolClassId', $class->id)
        ->call('createPlan');

    $component->call('renamePlan', 'Plan de classe');

    expect(SeatingPlan::query()->where('school_class_id', $class->id)->where('name', 'Plan 1')->exists())->toBeTrue();
});

it('deletes a plan and falls back to a fresh default plan', function () {
    $teacher = User::factory()->create();
    $class = SchoolClass::factory()->for($teacher)->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingChart::class)->set('schoolClassId', $class->id);
    $planId = $component->get('planId');

    $component->call('deletePlan');

    expect(SeatingPlan::query()->find($planId))->toBeNull()
        ->and(SeatingPlan::query()->where('school_class_id', $class->id)->count())->toBe(1);
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
    $otherClass = SchoolClass::factory()->for($otherTeacher)->create();
    $plan = SeatingPlan::factory()->for($otherTeacher)->for($otherClass, 'schoolClass')->create();

    expect($teacher->can('update', $plan))->toBeFalse()
        ->and($teacher->can('delete', $plan))->toBeFalse();
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
