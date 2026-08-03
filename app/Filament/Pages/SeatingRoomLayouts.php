<?php

namespace App\Filament\Pages;

use App\Models\SeatingPlan;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * "Disposition des salles": configures a reusable room layout — its name,
 * its desks, its teacher's desk position — with no class or date attached.
 * A layout only becomes a dated seating arrangement for a specific class on
 * the "Plans de classe" page (SeatingChart), which picks a layout, picks a
 * class, and never touches the desks themselves.
 */
class SeatingRoomLayouts extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Disposition des salles';

    protected static ?string $title = 'Disposition des salles';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.seating-room-layouts';

    public ?int $planId = null;

    public int $gridRows = 3;

    public int $gridCols = 4;

    public int $newDeskCapacity = 2;

    /** @var 'desk'|'blocked' */
    public string $newCellType = 'desk';

    public ?string $teacherDeskPosition = null;

    public function mount(): void
    {
        $this->selectDefaultPlan();
    }

    public function updatedPlanId(): void
    {
        $this->gridRows = 3;
        $this->gridCols = 4;
        $this->syncPlanFields();
    }

    private function selectDefaultPlan(): void
    {
        $this->gridRows = 3;
        $this->gridCols = 4;

        $plan = SeatingPlan::query()->orderBy('name')->first();

        if (! $plan) {
            $plan = new SeatingPlan(['name' => 'Plan de classe']);
            $plan->user_id = Auth::id();
            $plan->save();
        }

        $this->planId = $plan->id;
        $this->syncPlanFields();
    }

    private function syncPlanFields(): void
    {
        $plan = $this->resolvePlan();

        $this->teacherDeskPosition = $plan?->teacher_desk_position;
    }

    /**
     * @return Collection<int, SeatingPlan>
     */
    public function getPlansProperty(): Collection
    {
        return SeatingPlan::query()->orderBy('name')->get();
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

        return SeatingPlan::query()->find($this->planId);
    }

    /**
     * @return Collection<string, SeatingPlanDesk>
     */
    public function getDeskMapProperty(): Collection
    {
        if (! $this->planId) {
            return collect();
        }

        $desks = SeatingPlanDesk::query()
            ->where('seating_plan_id', $this->planId)
            ->orderBy('position_row')
            ->orderBy('position_col')
            ->get();

        return $desks->keyBy(fn (SeatingPlanDesk $desk) => $desk->position_row.'-'.$desk->position_col);
    }

    /**
     * @return array{rows: int, cols: int}
     */
    public function getGridSizeProperty(): array
    {
        $desks = $this->deskMap->values();
        $maxRow = $desks->max('position_row');
        $maxCol = $desks->max('position_col');

        return [
            'rows' => max($this->gridRows, $maxRow !== null ? $maxRow + 1 : 0),
            'cols' => max($this->gridCols, $maxCol !== null ? $maxCol + 1 : 0),
        ];
    }

    public function createPlan(): void
    {
        $plan = new SeatingPlan(['name' => $this->nextPlanName()]);
        $plan->user_id = Auth::id();
        $plan->save();

        $this->planId = $plan->id;
        $this->gridRows = 3;
        $this->gridCols = 4;
        $this->syncPlanFields();
    }

    public function duplicatePlan(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $newPlan = new SeatingPlan([
            'name' => $this->nextCopyName($plan->name),
            'teacher_desk_position' => $plan->teacher_desk_position,
        ]);
        $newPlan->user_id = Auth::id();
        $newPlan->save();

        foreach ($plan->desks as $desk) {
            SeatingPlanDesk::create([
                'seating_plan_id' => $newPlan->id,
                'position_row' => $desk->position_row,
                'position_col' => $desk->position_col,
                'capacity' => $desk->capacity,
                'is_blocked' => $desk->is_blocked,
            ]);
        }

        $this->planId = $newPlan->id;
        $this->gridRows = 3;
        $this->gridCols = 4;
        $this->syncPlanFields();
    }

    public function renamePlan(string $name): void
    {
        $plan = $this->resolvePlan();
        $name = trim($name);

        if (! $plan || $name === '') {
            return;
        }

        $exists = SeatingPlan::query()
            ->where('name', $name)
            ->where('id', '!=', $plan->id)
            ->exists();

        if ($exists) {
            Notification::make()->title('Une disposition porte déjà ce nom.')->danger()->send();

            return;
        }

        $plan->name = $name;
        $plan->save();
    }

    public function setTeacherDeskPosition(?string $position): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $plan->teacher_desk_position = in_array($position, ['left', 'center', 'right'], true) ? $position : null;
        $plan->save();

        $this->teacherDeskPosition = $plan->teacher_desk_position;
    }

    public function deletePlan(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        $plan->delete();

        $this->selectDefaultPlan();
    }

    public function addRow(): void
    {
        $this->gridRows = min($this->gridRows + 1, 12);
    }

    public function addColumn(): void
    {
        $this->gridCols = min($this->gridCols + 1, 12);
    }

    public function removeRow(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        // Deliberately not $this->gridSize here: that computed property
        // memoizes for the rest of the request the moment it's read, so if
        // the desk delete below ran first, the render at the end of this
        // same request would still show the row we just removed.
        $maxDeskRow = SeatingPlanDesk::query()->where('seating_plan_id', $plan->id)->max('position_row');
        $lastRow = max($this->gridRows, $maxDeskRow !== null ? $maxDeskRow + 1 : 0) - 1;

        if ($lastRow < 0) {
            return;
        }

        SeatingPlanDesk::query()->where('seating_plan_id', $plan->id)->where('position_row', $lastRow)->delete();

        $this->gridRows = max(1, $this->gridRows - 1);
    }

    public function removeColumn(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        // See removeRow(): must not read the memoized $this->gridSize before
        // the delete, or the render at the end of this request goes stale.
        $maxDeskCol = SeatingPlanDesk::query()->where('seating_plan_id', $plan->id)->max('position_col');
        $lastCol = max($this->gridCols, $maxDeskCol !== null ? $maxDeskCol + 1 : 0) - 1;

        if ($lastCol < 0) {
            return;
        }

        SeatingPlanDesk::query()->where('seating_plan_id', $plan->id)->where('position_col', $lastCol)->delete();

        $this->gridCols = max(1, $this->gridCols - 1);
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
            'capacity' => in_array($this->newDeskCapacity, [1, 2, 3], true) ? $this->newDeskCapacity : 2,
            'is_blocked' => $this->newCellType === 'blocked',
        ]);
    }

    public function removeDesk(int $deskId): void
    {
        $plan = $this->resolvePlan();

        if (! $plan) {
            return;
        }

        SeatingPlanDesk::query()->where('id', $deskId)->where('seating_plan_id', $plan->id)->delete();
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

        // Blocked slots can be sized down to a single seat (e.g. one reserved
        // spot rather than a whole table); normal desks stay 2↔3, matching
        // the two real bench sizes a classroom actually has.
        $newCapacity = $desk->is_blocked
            ? match ($desk->capacity) {
                1 => 2,
                2 => 3,
                default => 1,
            }
        : ($desk->capacity === 3 ? 2 : 3);

        if ($newCapacity < $desk->capacity) {
            SeatingPlanSeat::query()
                ->where('seating_plan_desk_id', $desk->id)
                ->where('seat_index', '>=', $newCapacity)
                ->delete();
        }

        $desk->capacity = $newCapacity;
        $desk->save();
    }

    private function nextPlanName(): string
    {
        $existingNames = SeatingPlan::query()->pluck('name');
        $index = 1;

        do {
            $name = 'Plan '.$index;
            $index++;
        } while ($existingNames->contains($name));

        return $name;
    }

    private function nextCopyName(string $baseName): string
    {
        $existingNames = SeatingPlan::query()->pluck('name');
        $candidate = $baseName.' (copie)';
        $suffix = 2;

        while ($existingNames->contains($candidate)) {
            $candidate = $baseName.' (copie '.$suffix.')';
            $suffix++;
        }

        return $candidate;
    }
}
