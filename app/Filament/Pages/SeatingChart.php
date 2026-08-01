<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\SeatingPlan;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use App\Models\Student;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SeatingChart extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Plan de classe';

    protected static ?string $title = 'Plan de classe';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.seating-chart';

    public ?int $schoolClassId = null;

    public ?int $planId = null;

    public ?int $selectedStudentId = null;

    public int $gridRows = 3;

    public int $gridCols = 4;

    public int $newDeskCapacity = 2;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->value('id');

        $this->selectPlanForClass();
    }

    public function updatedSchoolClassId(): void
    {
        $this->selectPlanForClass();
    }

    public function updatedPlanId(): void
    {
        $this->selectedStudentId = null;
    }

    private function selectPlanForClass(): void
    {
        $this->selectedStudentId = null;
        $this->gridRows = 3;
        $this->gridCols = 4;

        if (! $this->schoolClassId) {
            $this->planId = null;

            return;
        }

        $plan = SeatingPlan::query()->where('school_class_id', $this->schoolClassId)->orderBy('id')->first();

        if (! $plan) {
            $plan = new SeatingPlan(['school_class_id' => $this->schoolClassId, 'name' => 'Plan de classe']);
            $plan->user_id = Auth::id();
            $plan->save();
        }

        $this->planId = $plan->id;
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    public function getSchoolClassesProperty(): Collection
    {
        return SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, SeatingPlan>
     */
    public function getPlansProperty(): Collection
    {
        if (! $this->schoolClassId) {
            return collect();
        }

        return SeatingPlan::query()
            ->where('school_class_id', $this->schoolClassId)
            ->orderBy('name')
            ->get();
    }

    public function getCurrentPlanProperty(): ?SeatingPlan
    {
        return $this->resolvePlan();
    }

    /**
     * Fresh, uncached read of the current plan. Action methods must use this
     * instead of the `currentPlan` computed property: Livewire memoizes
     * `get*Property()` accessors for the lifetime of the request, so an
     * action that both accesses `currentPlan` and mutates the database would
     * leave the subsequent render reading pre-mutation data.
     */
    private function resolvePlan(): ?SeatingPlan
    {
        if (! $this->planId) {
            return null;
        }

        return SeatingPlan::query()->with(['desks.seats.student'])->find($this->planId);
    }

    /**
     * @return Collection<string, SeatingPlanDesk>
     */
    public function getDeskMapProperty(): Collection
    {
        $plan = $this->currentPlan;

        if (! $plan) {
            return collect();
        }

        return $plan->desks->keyBy(fn (SeatingPlanDesk $desk) => $desk->position_row.'-'.$desk->position_col);
    }

    /**
     * @return array{rows: int, cols: int}
     */
    public function getGridSizeProperty(): array
    {
        $plan = $this->currentPlan;
        $maxRow = $plan?->desks->max('position_row');
        $maxCol = $plan?->desks->max('position_col');

        return [
            'rows' => max($this->gridRows, $maxRow !== null ? $maxRow + 1 : 0),
            'cols' => max($this->gridCols, $maxCol !== null ? $maxCol + 1 : 0),
        ];
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudentsProperty(): Collection
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return collect();
        }

        return $schoolClass->students()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * @return Collection<int, Student>
     */
    public function getUnassignedStudentsProperty(): Collection
    {
        $plan = $this->currentPlan;
        $seatedIds = $plan?->desks->flatMap(fn (SeatingPlanDesk $desk) => $desk->seats->pluck('student_id'))->all() ?? [];

        return $this->getStudentsProperty()->reject(fn (Student $student) => in_array($student->id, $seatedIds, true))->values();
    }

    public function createPlan(): void
    {
        if (! $this->schoolClassId) {
            return;
        }

        $plan = new SeatingPlan(['school_class_id' => $this->schoolClassId, 'name' => $this->nextPlanName()]);
        $plan->user_id = Auth::id();
        $plan->save();

        $this->planId = $plan->id;
        $this->selectedStudentId = null;
    }

    public function duplicatePlan(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $newPlan = new SeatingPlan(['school_class_id' => $plan->school_class_id, 'name' => $this->nextCopyName($plan->name)]);
        $newPlan->user_id = Auth::id();
        $newPlan->save();

        foreach ($plan->desks as $desk) {
            $newDesk = SeatingPlanDesk::create([
                'seating_plan_id' => $newPlan->id,
                'position_row' => $desk->position_row,
                'position_col' => $desk->position_col,
                'capacity' => $desk->capacity,
            ]);

            foreach ($desk->seats as $seat) {
                SeatingPlanSeat::create([
                    'seating_plan_id' => $newPlan->id,
                    'seating_plan_desk_id' => $newDesk->id,
                    'seat_index' => $seat->seat_index,
                    'student_id' => $seat->student_id,
                ]);
            }
        }

        $this->planId = $newPlan->id;
        $this->selectedStudentId = null;
    }

    public function renamePlan(string $name): void
    {
        $plan = $this->resolvePlan();
        $name = trim($name);

        if (! $plan || $name === '') {
            return;
        }

        $exists = SeatingPlan::query()
            ->where('school_class_id', $plan->school_class_id)
            ->where('name', $name)
            ->where('id', '!=', $plan->id)
            ->exists();

        if ($exists) {
            Notification::make()->title('Un plan porte déjà ce nom.')->danger()->send();

            return;
        }

        $plan->name = $name;
        $plan->save();
    }

    public function deletePlan(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $plan->delete();

        $this->selectPlanForClass();
    }

    public function addRow(): void
    {
        $this->gridRows = min($this->gridRows + 1, 12);
    }

    public function addColumn(): void
    {
        $this->gridCols = min($this->gridCols + 1, 12);
    }

    public function addDesk(int $row, int $col): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $exists = SeatingPlanDesk::query()
            ->where('seating_plan_id', $plan->id)
            ->where('position_row', $row)
            ->where('position_col', $col)
            ->exists();

        if ($exists) {
            return;
        }

        SeatingPlanDesk::create([
            'seating_plan_id' => $plan->id,
            'position_row' => $row,
            'position_col' => $col,
            'capacity' => in_array($this->newDeskCapacity, [2, 3], true) ? $this->newDeskCapacity : 2,
        ]);
    }

    public function removeDesk(int $deskId): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        SeatingPlanDesk::query()->where('id', $deskId)->where('seating_plan_id', $plan->id)->delete();

        $this->selectedStudentId = null;
    }

    public function toggleDeskCapacity(int $deskId): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $desk = SeatingPlanDesk::query()->where('id', $deskId)->where('seating_plan_id', $plan->id)->first();

        if (! $desk) {
            return;
        }

        $newCapacity = $desk->capacity === 3 ? 2 : 3;

        if ($newCapacity < $desk->capacity) {
            SeatingPlanSeat::query()
                ->where('seating_plan_desk_id', $desk->id)
                ->where('seat_index', '>=', $newCapacity)
                ->delete();
        }

        $desk->capacity = $newCapacity;
        $desk->save();
    }

    public function selectStudent(int $studentId): void
    {
        $this->selectedStudentId = $this->selectedStudentId === $studentId ? null : $studentId;
    }

    public function seatClicked(int $deskId, int $seatIndex): void
    {
        if ($this->selectedStudentId) {
            $this->placeSelected($deskId, $seatIndex);

            return;
        }

        $seat = SeatingPlanSeat::query()
            ->where('seating_plan_desk_id', $deskId)
            ->where('seat_index', $seatIndex)
            ->first();

        if ($seat) {
            $this->selectedStudentId = $seat->student_id;
        }
    }

    private function placeSelected(int $deskId, int $seatIndex): void
    {
        $plan = $this->resolvePlan();

        if (! $plan || ! $this->selectedStudentId) {
            return;
        }

        $desk = SeatingPlanDesk::query()->where('id', $deskId)->where('seating_plan_id', $plan->id)->first();

        if (! $desk || $seatIndex >= $desk->capacity) {
            return;
        }

        $studentId = $this->selectedStudentId;

        $previousSeat = SeatingPlanSeat::query()
            ->where('seating_plan_id', $plan->id)
            ->where('student_id', $studentId)
            ->first();

        $targetSeat = SeatingPlanSeat::query()
            ->where('seating_plan_desk_id', $deskId)
            ->where('seat_index', $seatIndex)
            ->first();

        if ($previousSeat && $targetSeat && $previousSeat->id === $targetSeat->id) {
            $this->selectedStudentId = null;

            return;
        }

        $displacedStudentId = $targetSeat?->student_id;
        $previousDeskId = $previousSeat?->seating_plan_desk_id;
        $previousSeatIndex = $previousSeat?->seat_index;

        $previousSeat?->delete();
        $targetSeat?->delete();

        SeatingPlanSeat::create([
            'seating_plan_id' => $plan->id,
            'seating_plan_desk_id' => $deskId,
            'seat_index' => $seatIndex,
            'student_id' => $studentId,
        ]);

        if ($displacedStudentId && $previousDeskId !== null) {
            SeatingPlanSeat::create([
                'seating_plan_id' => $plan->id,
                'seating_plan_desk_id' => $previousDeskId,
                'seat_index' => $previousSeatIndex,
                'student_id' => $displacedStudentId,
            ]);
        }

        $this->selectedStudentId = null;
    }

    public function randomize(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        SeatingPlanSeat::query()->where('seating_plan_id', $plan->id)->delete();

        $desks = $plan->desks()->orderBy('position_row')->orderBy('position_col')->get();
        $students = $this->getStudentsProperty()->shuffle()->values();

        $studentIndex = 0;

        foreach ($desks as $desk) {
            for ($seatIndex = 0; $seatIndex < $desk->capacity; $seatIndex++) {
                if ($studentIndex >= $students->count()) {
                    break 2;
                }

                SeatingPlanSeat::create([
                    'seating_plan_id' => $plan->id,
                    'seating_plan_desk_id' => $desk->id,
                    'seat_index' => $seatIndex,
                    'student_id' => $students[$studentIndex]->id,
                ]);

                $studentIndex++;
            }
        }

        $this->selectedStudentId = null;
    }

    public function clearSeats(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        SeatingPlanSeat::query()->where('seating_plan_id', $plan->id)->delete();

        $this->selectedStudentId = null;
    }

    private function nextPlanName(): string
    {
        $existingNames = SeatingPlan::query()->where('school_class_id', $this->schoolClassId)->pluck('name');
        $index = 1;

        do {
            $name = 'Plan '.$index;
            $index++;
        } while ($existingNames->contains($name));

        return $name;
    }

    private function nextCopyName(string $baseName): string
    {
        $existingNames = SeatingPlan::query()->where('school_class_id', $this->schoolClassId)->pluck('name');
        $candidate = $baseName.' (copie)';
        $suffix = 2;

        while ($existingNames->contains($candidate)) {
            $candidate = $baseName.' (copie '.$suffix.')';
            $suffix++;
        }

        return $candidate;
    }
}
