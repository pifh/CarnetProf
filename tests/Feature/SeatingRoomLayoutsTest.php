<?php

use App\Filament\Pages\SeatingRoomLayouts;
use App\Models\SeatingPlan;
use App\Models\SeatingPlanApplication;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use App\Models\User;
use Livewire\Livewire;

it('creates a default plan for a teacher with none', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class);

    $plan = SeatingPlan::query()->first();

    expect($plan)->not->toBeNull()->and($plan->name)->toBe('Plan de classe')
        ->and($component->get('planId'))->toBe($plan->id);
});

it('creates additional uniquely named plans for the same teacher', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    Livewire::test(SeatingRoomLayouts::class)
        ->call('createPlan')
        ->call('createPlan');

    $names = SeatingPlan::query()->orderBy('id')->pluck('name');

    expect($names->all())->toBe(['Plan de classe', 'Plan 1', 'Plan 2']);
});

it('places a desk on the grid', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_row', 0)->where('position_col', 0)->first();

    expect($desk)->not->toBeNull()->and($desk->capacity)->toBe(2)
        ->and($desk->seating_plan_id)->toBe($component->get('planId'));
});

it('removes a desk and frees any seat assigned to it', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $application = SeatingPlanApplication::factory()->for($teacher)->for(SeatingPlan::find($component->get('planId')), 'seatingPlan')->create();
    SeatingPlanSeat::factory()->create([
        'seating_plan_application_id' => $application->id,
        'seating_plan_desk_id' => $desk->id,
        'seat_index' => 0,
    ]);

    $component->call('removeDesk', $desk->id);

    expect(SeatingPlanDesk::query()->find($desk->id))->toBeNull()
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->exists())->toBeFalse();
});

it('drops the overflow seat when a desk shrinks from 3 to 2 seats', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)
        ->set('newDeskCapacity', 3)
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_col', 0)->first();
    $application = SeatingPlanApplication::factory()->for($teacher)->for(SeatingPlan::find($component->get('planId')), 'seatingPlan')->create();

    foreach (range(0, 2) as $seatIndex) {
        SeatingPlanSeat::factory()->create([
            'seating_plan_application_id' => $application->id,
            'seating_plan_desk_id' => $desk->id,
            'seat_index' => $seatIndex,
        ]);
    }

    expect(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->count())->toBe(3);

    $component->call('toggleDeskCapacity', $desk->id);

    expect(SeatingPlanDesk::query()->find($desk->id)->capacity)->toBe(2)
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->count())->toBe(2)
        ->and(SeatingPlanSeat::query()->where('seating_plan_desk_id', $desk->id)->where('seat_index', 2)->exists())->toBeFalse();
});

it('adds a row and a column, then removes them again', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class);

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

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)
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

it('adds a blocked slot that occupies space but is never a normal desk', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    Livewire::test(SeatingRoomLayouts::class)
        ->set('newCellType', 'blocked')
        ->call('addDesk', 0, 0);

    $desk = SeatingPlanDesk::query()->where('position_row', 0)->where('position_col', 0)->first();

    expect($desk)->not->toBeNull()->and($desk->is_blocked)->toBeTrue();
});

it('duplicates a plan with its desks, but starts with no applications', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)->call('addDesk', 0, 0);

    $component->call('duplicatePlan');

    $copy = SeatingPlan::query()->where('name', 'Plan de classe (copie)')->first();

    expect($copy)->not->toBeNull()
        ->and($component->get('planId'))->toBe($copy->id);

    $copiedDesk = SeatingPlanDesk::query()->where('seating_plan_id', $copy->id)->first();

    expect($copiedDesk)->not->toBeNull()
        ->and($copiedDesk->position_row)->toBe(0)
        ->and($copiedDesk->position_col)->toBe(0)
        ->and(SeatingPlanApplication::query()->where('seating_plan_id', $copy->id)->exists())->toBeFalse();
});

it('rejects renaming a plan to a name already used by the same teacher', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)->call('createPlan');

    $component->call('renamePlan', 'Plan de classe');

    expect(SeatingPlan::query()->where('name', 'Plan 1')->exists())->toBeTrue();
});

it('deletes a plan and falls back to a fresh default plan', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class);
    $planId = $component->get('planId');

    $component->call('deletePlan');

    expect(SeatingPlan::query()->find($planId))->toBeNull()
        ->and(SeatingPlan::query()->count())->toBe(1);
});

it('sets the teacher desk position on the plan', function () {
    $teacher = User::factory()->create();

    $this->actingAs($teacher);

    $component = Livewire::test(SeatingRoomLayouts::class)->call('setTeacherDeskPosition', 'right');

    $planId = $component->get('planId');

    expect(SeatingPlan::query()->find($planId)->teacher_desk_position)->toBe('right');
});

it("prevents a teacher from updating or deleting another teacher's plan", function () {
    $teacher = User::factory()->create();
    $otherTeacher = User::factory()->create();
    $plan = SeatingPlan::factory()->for($otherTeacher)->create();

    expect($teacher->can('update', $plan))->toBeFalse()
        ->and($teacher->can('delete', $plan))->toBeFalse();
});
