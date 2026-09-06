<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\SeatingPlan;
use App\Models\SeatingPlanApplication;
use App\Models\SeatingPlanDesk;
use App\Models\SeatingPlanSeat;
use App\Models\Student;
use App\Services\SeatingAssigner;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Plans de classe": pick a room layout (configured on the "Disposition des
 * salles" page — SeatingRoomLayouts) and a class, then place students for a
 * given date. Each (layout, class, date) triple is a SeatingPlanApplication
 * — its own seat assignments, its own locked/blocked seats, its own archive
 * state. This page never edits desks: the room layout is read-only here,
 * browsed via the date sidebar to make it easy to look back at previous
 * arrangements for the same room and class before creating a new one.
 */
class SeatingChart extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Plans de classe';

    protected static ?string $title = 'Plans de classe';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.seating-chart';

    public ?int $schoolClassId = null;

    public ?int $planId = null;

    public ?int $applicationId = null;

    public ?int $selectedStudentId = null;

    public bool $showArchivedApplications = false;

    public string $effectiveDate = '';

    public ?string $teacherDeskPosition = null;

    public bool $avoidSameSexNeighbors = false;

    public bool $avoidRepeatSeats = false;

    public bool $avoidRepeatNeighbors = false;

    public bool $heightOrdering = false;

    public int $heightMarginCm = 10;

    public bool $printTeacherView = false;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->hasSubjects()
            ->orderBy('name')
            ->value('id');

        $this->planId = SeatingPlan::query()->orderBy('name')->value('id');

        $this->syncPlanFields();
        $this->selectApplicationForPlanAndClass();
    }

    public function updatedSchoolClassId(): void
    {
        $this->selectedStudentId = null;
        $this->showArchivedApplications = false;
        $this->selectApplicationForPlanAndClass();
    }

    public function updatedPlanId(): void
    {
        $this->selectedStudentId = null;
        $this->showArchivedApplications = false;
        $this->syncPlanFields();
        $this->selectApplicationForPlanAndClass();
    }

    public function updatedShowArchivedApplications(): void
    {
        $applications = $this->getApplicationsProperty();

        if (! $applications->contains('id', $this->applicationId)) {
            $this->applicationId = $applications->first()?->id;
            $this->syncApplicationFields();
        }
    }

    /**
     * Called when the teacher clicks a date in the sidebar.
     */
    public function selectApplication(int $applicationId): void
    {
        $exists = SeatingPlanApplication::query()
            ->where('id', $applicationId)
            ->where('seating_plan_id', $this->planId)
            ->where('school_class_id', $this->schoolClassId)
            ->exists();

        if (! $exists) {
            return;
        }

        $this->applicationId = $applicationId;
        $this->selectedStudentId = null;
        $this->syncApplicationFields();
    }

    private function selectApplicationForPlanAndClass(): void
    {
        if (! $this->planId || ! $this->schoolClassId) {
            $this->applicationId = null;

            return;
        }

        $application = SeatingPlanApplication::query()
            ->where('seating_plan_id', $this->planId)
            ->where('school_class_id', $this->schoolClassId)
            ->where('is_archived', false)
            ->orderByDesc('effective_date')
            ->orderBy('id')
            ->first();

        if (! $application) {
            $application = new SeatingPlanApplication([
                'seating_plan_id' => $this->planId,
                'school_class_id' => $this->schoolClassId,
                'effective_date' => now()->toDateString(),
            ]);
            $application->user_id = Auth::id();
            $application->save();
        }

        $this->applicationId = $application->id;
        $this->syncApplicationFields();
    }

    private function syncPlanFields(): void
    {
        $plan = $this->resolvePlan();

        $this->teacherDeskPosition = $plan?->teacher_desk_position;
    }

    private function syncApplicationFields(): void
    {
        $application = $this->resolveApplication();

        $this->effectiveDate = $application?->effective_date?->format('Y-m-d') ?? '';
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    public function getSchoolClassesProperty(): Collection
    {
        return SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->hasSubjects()
            ->orderBy('name')
            ->get();
    }

    /**
     * Every layout the teacher has, independent of any class — a layout is a
     * reusable room configuration, picked here and then paired with a class.
     *
     * @return Collection<int, SeatingPlan>
     */
    public function getPlansProperty(): Collection
    {
        return SeatingPlan::query()->orderBy('name')->get();
    }

    /**
     * Every dated use of the current (layout, class) pair, most recent
     * first — the sidebar's whole reason for existing: making it easy to
     * look back before creating the next one.
     *
     * @return Collection<int, SeatingPlanApplication>
     */
    public function getApplicationsProperty(): Collection
    {
        if (! $this->planId || ! $this->schoolClassId) {
            return collect();
        }

        return SeatingPlanApplication::query()
            ->where('seating_plan_id', $this->planId)
            ->where('school_class_id', $this->schoolClassId)
            ->when(! $this->showArchivedApplications, fn ($query) => $query->where('is_archived', false))
            ->orderByDesc('effective_date')
            ->orderBy('id')
            ->get();
    }

    public function getCurrentPlanProperty(): ?SeatingPlan
    {
        return $this->resolvePlan();
    }

    public function getCurrentApplicationProperty(): ?SeatingPlanApplication
    {
        return $this->resolveApplication();
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

    private function resolveApplication(): ?SeatingPlanApplication
    {
        if (! $this->applicationId) {
            return null;
        }

        return SeatingPlanApplication::query()->find($this->applicationId);
    }

    /**
     * The plan's desks, each carrying only the seats that belong to the
     * *current application* — desks are shared room layout, but who (or
     * what block) sits where is application-specific.
     *
     * @return Collection<string, SeatingPlanDesk>
     */
    public function getDeskMapProperty(): Collection
    {
        if (! $this->planId) {
            return collect();
        }

        $desks = SeatingPlanDesk::query()
            ->where('seating_plan_id', $this->planId)
            ->with(['seats' => fn ($query) => $query
                ->where('seating_plan_application_id', $this->applicationId)
                ->with('student')])
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
            'rows' => $maxRow !== null ? $maxRow + 1 : 0,
            'cols' => $maxCol !== null ? $maxCol + 1 : 0,
        ];
    }

    /**
     * Cumulative capacity of every desk before a given desk in its row,
     * blocked slots included — an absolute "which individual bureau is this"
     * index, as opposed to `position_col` which only counts desk clusters.
     *
     * @param  Collection<int, SeatingPlanDesk>  $desks
     * @return array<int, int> deskId => base column (0-indexed)
     */
    private function computeBaseColumns(Collection $desks): array
    {
        $offsets = [];

        foreach ($desks->groupBy('position_row') as $rowDesks) {
            $cumulative = 0;

            foreach ($rowDesks->sortBy('position_col') as $desk) {
                $offsets[$desk->id] = $cumulative;
                $cumulative += $desk->capacity;
            }
        }

        return $offsets;
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

        return $schoolClass->allStudents()
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
        $seatedIds = $this->deskMap->values()
            ->flatMap(fn (SeatingPlanDesk $desk) => $desk->seats->pluck('student_id'))
            ->filter()
            ->all();

        return $this->getStudentsProperty()->reject(fn (Student $student) => in_array($student->id, $seatedIds, true))->values();
    }

    /**
     * Which placement rules the application's current seating breaks,
     * independent of how it was placed (manual clicks or "Répartir
     * aléatoirement") — computed fresh from the persisted seats every
     * render, so a manual move that creates a violation is flagged
     * immediately.
     *
     * @return array<int, string[]> studentId => violation messages
     */
    public function getViolationsProperty(): array
    {
        if (! $this->applicationId) {
            return [];
        }

        $desks = $this->deskMap->values();

        $deskByStudent = [];
        $seatIndexByStudent = [];
        foreach ($desks as $desk) {
            foreach ($desk->seats as $seat) {
                if ($seat->student_id) {
                    $deskByStudent[$seat->student_id] = $desk;
                    $seatIndexByStudent[$seat->student_id] = $seat->seat_index;
                }
            }
        }

        if ($deskByStudent === []) {
            return [];
        }

        $baseColumns = $this->computeBaseColumns($desks);

        $studentIds = array_keys($deskByStudent);
        $students = Student::query()->whereIn('id', $studentIds)->get()->keyBy('id');

        [$nextToPairs, $notNextToPairs, $farFromPairs] = $this->gatherSeatingPairs($studentIds);

        $violations = [];
        $flag = function (int $studentId, string $message) use (&$violations): void {
            $violations[$studentId][] = $message;
        };

        foreach ($nextToPairs as [$a, $b]) {
            if (isset($deskByStudent[$a], $deskByStudent[$b]) && $deskByStudent[$a]->id !== $deskByStudent[$b]->id) {
                $flag($a, 'Devrait être à côté de '.($students->get($b)?->full_name ?? '?'));
                $flag($b, 'Devrait être à côté de '.($students->get($a)?->full_name ?? '?'));
            }
        }

        foreach ($notNextToPairs as [$a, $b]) {
            if (isset($deskByStudent[$a], $deskByStudent[$b]) && $deskByStudent[$a]->id === $deskByStudent[$b]->id) {
                $flag($a, 'Ne devrait pas être à côté de '.($students->get($b)?->full_name ?? '?'));
                $flag($b, 'Ne devrait pas être à côté de '.($students->get($a)?->full_name ?? '?'));
            }
        }

        foreach ($farFromPairs as [$a, $b]) {
            if (isset($deskByStudent[$a], $deskByStudent[$b]) && $deskByStudent[$a]->id === $deskByStudent[$b]->id) {
                $flag($a, 'Devrait être séparé de '.($students->get($b)?->full_name ?? '?'));
                $flag($b, 'Devrait être séparé de '.($students->get($a)?->full_name ?? '?'));
            }
        }

        foreach ($deskByStudent as $studentId => $desk) {
            $student = $students->get($studentId);

            if (! $student) {
                continue;
            }

            if (! empty($student->seating_allowed_rows) && ! in_array($desk->position_row + 1, array_map('intval', $student->seating_allowed_rows), true)) {
                $flag($studentId, 'Rang non autorisé');
            }

            $absoluteColumn = ($baseColumns[$desk->id] ?? $desk->position_col) + ($seatIndexByStudent[$studentId] ?? 0);

            if (! empty($student->seating_allowed_columns) && ! in_array($absoluteColumn + 1, array_map('intval', $student->seating_allowed_columns), true)) {
                $flag($studentId, 'Colonne non autorisée');
            }
        }

        return $violations;
    }

    /**
     * A new dated use of the current (layout, class) pair. Starts as a copy
     * of the current application's seating (a convenient starting point the
     * teacher can then re-randomize or hand-edit) but is otherwise fully
     * independent: its own locks, its own blocked seats, its own archive
     * state, and its own place in the "avoid repeat seat/neighbor" history.
     */
    public function createApplication(): void
    {
        $plan = $this->resolvePlan();

        if (! $plan || ! $this->schoolClassId) {
            return;
        }

        $sourceApplication = $this->resolveApplication();

        $application = new SeatingPlanApplication([
            'seating_plan_id' => $plan->id,
            'school_class_id' => $this->schoolClassId,
            'effective_date' => now()->toDateString(),
        ]);
        $application->user_id = Auth::id();
        $application->save();

        if ($sourceApplication) {
            foreach ($sourceApplication->seats as $seat) {
                SeatingPlanSeat::create([
                    'seating_plan_application_id' => $application->id,
                    'seating_plan_desk_id' => $seat->seating_plan_desk_id,
                    'seat_index' => $seat->seat_index,
                    'student_id' => $seat->student_id,
                    'is_locked' => $seat->is_locked,
                    'is_blocked' => $seat->is_blocked,
                ]);
            }
        }

        $this->applicationId = $application->id;
        $this->selectedStudentId = null;
        $this->syncApplicationFields();
    }

    public function toggleArchiveApplication(): void
    {
        $application = $this->resolveApplication();

        if (! $application) {
            return;
        }

        $application->is_archived = ! $application->is_archived;
        $application->save();

        if ($application->is_archived && ! $this->showArchivedApplications) {
            $this->selectApplicationForPlanAndClass();
        }
    }

    public function deleteApplication(): void
    {
        $application = $this->resolveApplication();

        if (! $application) {
            return;
        }

        $application->delete();

        $this->selectApplicationForPlanAndClass();
    }

    public function updateEffectiveDate(?string $date): void
    {
        $application = $this->resolveApplication();

        if (! $application) {
            return;
        }

        $application->effective_date = filled($date) ? $date : null;
        $application->save();

        $this->effectiveDate = $date ?? '';
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
            ->where('seating_plan_application_id', $this->applicationId)
            ->where('seating_plan_desk_id', $deskId)
            ->where('seat_index', $seatIndex)
            ->first();

        if ($seat && ! $seat->is_locked && ! $seat->is_blocked) {
            $this->selectedStudentId = $seat->student_id;
        }
    }

    public function toggleSeatLock(int $deskId, int $seatIndex): void
    {
        if (! $this->applicationId) {
            return;
        }

        $seat = SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $this->applicationId)
            ->where('seating_plan_desk_id', $deskId)
            ->where('seat_index', $seatIndex)
            ->first();

        if (! $seat) {
            return;
        }

        $seat->is_locked = ! $seat->is_locked;
        $seat->save();

        if ($seat->is_locked && $this->selectedStudentId === $seat->student_id) {
            $this->selectedStudentId = null;
        }
    }

    /**
     * Blocks (or unblocks) an empty seat for the current application only —
     * the seat stays permanently unassigned when randomizing, the same way a
     * locked student's seat is pinned, but with no occupant. Distinct from a
     * whole desk marked "Emplacement vide" on the plan itself: that's a
     * permanent architectural void shared by every application, this is a
     * one-date decision (e.g. leaving a seat empty because the assigned
     * student is away).
     */
    public function toggleSeatBlock(int $deskId, int $seatIndex): void
    {
        $application = $this->resolveApplication();

        if (! $application) {
            return;
        }

        $desk = SeatingPlanDesk::query()->where('id', $deskId)->where('seating_plan_id', $application->seating_plan_id)->first();

        if (! $desk || $desk->is_blocked || $seatIndex >= $desk->capacity) {
            return;
        }

        $seat = SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('seating_plan_desk_id', $deskId)
            ->where('seat_index', $seatIndex)
            ->first();

        if ($seat && $seat->student_id) {
            return;
        }

        if ($seat && $seat->is_blocked) {
            $seat->delete();

            return;
        }

        SeatingPlanSeat::create([
            'seating_plan_application_id' => $application->id,
            'seating_plan_desk_id' => $deskId,
            'seat_index' => $seatIndex,
            'student_id' => null,
            'is_blocked' => true,
        ]);
    }

    private function placeSelected(int $deskId, int $seatIndex): void
    {
        $application = $this->resolveApplication();

        if (! $application || ! $this->selectedStudentId) {
            return;
        }

        $desk = SeatingPlanDesk::query()->where('id', $deskId)->where('seating_plan_id', $application->seating_plan_id)->first();

        if (! $desk || $desk->is_blocked || $seatIndex >= $desk->capacity) {
            return;
        }

        $studentId = $this->selectedStudentId;

        $previousSeat = SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('student_id', $studentId)
            ->first();

        $targetSeat = SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('seating_plan_desk_id', $deskId)
            ->where('seat_index', $seatIndex)
            ->first();

        if ($targetSeat && ($targetSeat->is_locked || $targetSeat->is_blocked)) {
            return;
        }

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
            'seating_plan_application_id' => $application->id,
            'seating_plan_desk_id' => $deskId,
            'seat_index' => $seatIndex,
            'student_id' => $studentId,
        ]);

        if ($displacedStudentId && $previousDeskId !== null) {
            SeatingPlanSeat::create([
                'seating_plan_application_id' => $application->id,
                'seating_plan_desk_id' => $previousDeskId,
                'seat_index' => $previousSeatIndex,
                'student_id' => $displacedStudentId,
            ]);
        }

        $this->selectedStudentId = null;
    }

    public function randomize(): void
    {
        $application = $this->resolveApplication();

        if (! $application) {
            return;
        }

        $desks = SeatingPlanDesk::query()->where('seating_plan_id', $application->seating_plan_id)->orderBy('position_row')->orderBy('position_col')->get();
        $assignableDesks = $desks->reject(fn (SeatingPlanDesk $desk) => $desk->is_blocked);

        $students = $this->getStudentsProperty();

        if ($assignableDesks->isEmpty() || $students->isEmpty()) {
            $this->selectedStudentId = null;

            return;
        }

        $lockedSeats = SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('is_locked', true)
            ->get(['student_id', 'seating_plan_desk_id', 'seat_index']);

        $lockedPlacements = $lockedSeats->pluck('seating_plan_desk_id', 'student_id')->all();
        $lockedSeatOffsets = $lockedSeats->pluck('seat_index', 'student_id')->all();

        // Seats blocked empty for this application stay reserved and never
        // assignable, exactly like a locked student — modeled the same way,
        // with a negative sentinel id standing in for "nobody" (real student
        // ids are always positive).
        $blockedSeats = SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('is_blocked', true)
            ->get(['seating_plan_desk_id', 'seat_index']);

        $blockedSentinels = [];
        $sentinelId = -1;
        foreach ($blockedSeats as $blocked) {
            $lockedPlacements[$sentinelId] = $blocked->seating_plan_desk_id;
            $lockedSeatOffsets[$sentinelId] = $blocked->seat_index;
            $blockedSentinels[] = $sentinelId;
            $sentinelId--;
        }

        SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('is_locked', false)
            ->where('is_blocked', false)
            ->delete();

        $baseColumns = $this->computeBaseColumns($desks);

        $deskData = $assignableDesks->mapWithKeys(fn (SeatingPlanDesk $desk) => [
            $desk->id => [
                'row' => $desk->position_row,
                'col' => $desk->position_col,
                'capacity' => $desk->capacity,
                'baseColumn' => $baseColumns[$desk->id],
            ],
        ])->all();

        $studentIds = [...$students->pluck('id')->all(), ...$blockedSentinels];

        // Teachers enter 1-based row/column numbers ("rang 1, 2, 3..."), the
        // grid itself is 0-indexed internally.
        $toGridIndexes = fn (array $values) => array_map(fn ($value) => max(0, ((int) $value) - 1), $values);

        $allowedRows = $students->filter(fn (Student $student) => filled($student->seating_allowed_rows))
            ->mapWithKeys(fn (Student $student) => [$student->id => $toGridIndexes($student->seating_allowed_rows)])
            ->all();

        $allowedColumns = $students->filter(fn (Student $student) => filled($student->seating_allowed_columns))
            ->mapWithKeys(fn (Student $student) => [$student->id => $toGridIndexes($student->seating_allowed_columns)])
            ->all();

        [$nextToPairs, $notNextToPairs, $farFromPairs] = $this->gatherSeatingPairs($students->pluck('id')->all());

        $sexes = $students->mapWithKeys(fn (Student $student) => [$student->id => $student->sex])->all();
        $heights = $students->mapWithKeys(fn (Student $student) => [$student->id => $student->height_cm])->all();

        [$previousSeatPositions, $previousNeighborCounts] = $this->gatherSeatingHistory($application, $students->pluck('id')->all());

        $result = app(SeatingAssigner::class)->assign(
            studentIds: $studentIds,
            desks: $deskData,
            allowedRows: $allowedRows,
            allowedColumns: $allowedColumns,
            nextToPairs: $nextToPairs,
            notNextToPairs: $notNextToPairs,
            farFromPairs: $farFromPairs,
            lockedPlacements: $lockedPlacements,
            lockedSeatOffsets: $lockedSeatOffsets,
            sexes: $sexes,
            avoidSameSexNeighbors: $this->avoidSameSexNeighbors,
            previousSeatPositions: $previousSeatPositions,
            avoidRepeatSeats: $this->avoidRepeatSeats,
            previousNeighborCounts: $previousNeighborCounts,
            avoidRepeatNeighbors: $this->avoidRepeatNeighbors,
            heights: $heights,
            heightOrdering: $this->heightOrdering,
            heightMarginCm: $this->heightMarginCm,
        );

        foreach ($result->placements as $studentId => $deskId) {
            if (array_key_exists($studentId, $lockedPlacements)) {
                continue;
            }

            SeatingPlanSeat::create([
                'seating_plan_application_id' => $application->id,
                'seating_plan_desk_id' => $deskId,
                'seat_index' => $result->seatOffsets[$studentId],
                'student_id' => $studentId,
            ]);
        }

        if ($result->unresolvedConflicts !== []) {
            Notification::make()
                ->title("Certaines consignes n'ont pas pu être respectées")
                ->body($this->describeConflicts($result->unresolvedConflicts))
                ->warning()
                ->send();
        }

        $this->selectedStudentId = null;
    }

    /**
     * @param  int[]  $studentIds
     * @return array{0: array<int, array{0: int, 1: int}>, 1: array<int, array{0: int, 1: int}>, 2: array<int, array{0: int, 1: int}>}
     */
    private function gatherSeatingPairs(array $studentIds): array
    {
        $rows = DB::table('student_seating_pairs')
            ->whereIn('student_id', $studentIds)
            ->whereIn('related_student_id', $studentIds)
            ->get(['student_id', 'related_student_id', 'type']);

        $pairs = ['next_to' => [], 'not_next_to' => [], 'far_from' => []];

        foreach ($rows as $row) {
            $pairs[$row->type][] = [(int) $row->student_id, (int) $row->related_student_id];
        }

        return [$pairs['next_to'], $pairs['not_next_to'], $pairs['far_from']];
    }

    /**
     * Builds history for this application's plan — every OTHER application of
     * the *same* plan (including archived ones, since "keep every previous
     * application" is the whole point of archiving them) contributes to
     * which desk position each student has already occupied, and which
     * pairs have already shared a desk. A different plan (a genuinely
     * different room layout) never contributes: its desk positions don't
     * mean the same thing.
     *
     * @param  int[]  $studentIds
     * @return array{0: array<int, string[]>, 1: array<string, int>}
     */
    private function gatherSeatingHistory(SeatingPlanApplication $currentApplication, array $studentIds): array
    {
        if ($studentIds === []) {
            return [[], []];
        }

        $seats = SeatingPlanSeat::query()
            ->whereHas('application', fn ($query) => $query
                ->where('seating_plan_id', $currentApplication->seating_plan_id)
                ->where('id', '!=', $currentApplication->id))
            ->whereIn('student_id', $studentIds)
            ->with('desk')
            ->get();

        $previousSeatPositions = [];
        $studentsByApplicationDesk = [];

        foreach ($seats as $seat) {
            if (! $seat->desk) {
                continue;
            }

            $previousSeatPositions[$seat->student_id][] = $seat->desk->position_row.'-'.$seat->desk->position_col;
            $studentsByApplicationDesk[$seat->seating_plan_application_id.'-'.$seat->seating_plan_desk_id][] = $seat->student_id;
        }

        $previousNeighborCounts = [];

        foreach ($studentsByApplicationDesk as $members) {
            $n = count($members);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $key = SeatingAssigner::pairKey($members[$i], $members[$j]);
                    $previousNeighborCounts[$key] = ($previousNeighborCounts[$key] ?? 0) + 1;
                }
            }
        }

        return [$previousSeatPositions, $previousNeighborCounts];
    }

    /**
     * @param  array<int, array{type: string, pair?: array{0: int, 1: int}, members?: int[]}>  $conflicts
     */
    private function describeConflicts(array $conflicts): string
    {
        $students = $this->getStudentsProperty()->keyBy('id');
        $name = fn (int $id) => $students->get($id)?->full_name ?? '?';

        $lines = collect($conflicts)->map(function (array $conflict) use ($name) {
            return match ($conflict['type']) {
                'next_to_vs_conflict' => 'Contrainte contradictoire entre '.$name($conflict['pair'][0]).' et '.$name($conflict['pair'][1]).' (à côté de / pas à côté de).',
                'next_to_too_large' => 'Groupe à garder ensemble trop grand pour un bureau : '.collect($conflict['members'])->map($name)->implode(', ').'.',
                'locked_conflict' => 'Élèves verrouillés à des bureaux différents alors qu\'ils doivent être ensemble : '.collect($conflict['members'])->map($name)->implode(', ').'.',
                'insufficient_desks' => 'Pas assez de places : '.collect($conflict['members'])->map($name)->implode(', ').' n\'ont pas pu être placé(e)s.',
                default => 'Une contrainte n\'a pas pu être respectée.',
            };
        });

        return $lines->implode(' ');
    }

    public function downloadPdf()
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $application = $this->resolveApplication();

        if (! $schoolClass || ! $application) {
            return null;
        }

        $gridSize = $this->getGridSizeProperty();
        $deskMap = $this->getDeskMapProperty();

        // "Vue du prof" is a full 180° turn of the room: the row nearest the
        // teacher's desk ends up farthest on the page (and vice versa), and
        // every row reads right-to-left instead of left-to-right — matching
        // what the teacher actually sees standing at their own desk facing
        // the students, instead of the default "vue des élèves" (the desk
        // out in front, as edited on the room layout).
        $rows = $this->printTeacherView
            ? range($gridSize['rows'] - 1, 0)
            : range(0, $gridSize['rows'] - 1);

        $rowsOfDesks = [];
        foreach ($rows as $row) {
            $rowDesks = $deskMap->values()->where('position_row', $row);
            $rowDesks = $this->printTeacherView
                ? $rowDesks->sortByDesc('position_col')->values()
                : $rowDesks->sortBy('position_col')->values();

            if ($rowDesks->isNotEmpty()) {
                $rowsOfDesks[] = $rowDesks;
            }
        }

        $maxRowUnits = collect($rowsOfDesks)->map(fn ($rowDesks) => $rowDesks->sum('capacity'))->max() ?? 0;
        $maxDesksInRow = collect($rowsOfDesks)->map(fn ($rowDesks) => $rowDesks->count())->max() ?? 0;
        $rowCount = count($rowsOfDesks);

        // A4 landscape usable area (297×210mm minus the 6mm page margins),
        // minus roughly the space the teacher-desk banner takes above the
        // grid and the footer below it, and a small safety margin — seats
        // are stretched to fill that area, so the plan never sits shrunk in
        // a corner of the page.
        $availableWidthMm = 283.0;
        $availableHeightMm = 196.0 - ($this->teacherDeskPosition ? 17.0 : 0.0) - 6.0;
        $deskGapMm = 6.0;
        $rowGapMm = 8.0;

        // Each seat's own padding and border sit outside the width/height we
        // set on it (content-box sizing — box-sizing: border-box doesn't
        // reliably apply to table cells), so that overhead has to come back
        // out of the raw per-seat share before it's used as the CSS value.
        $cellOverheadMm = 2.6;

        // Every desk carries its own 6mm trailing gap (including the last
        // one in a row), so the row's total gap budget is one per desk, not
        // one per gap between them.
        $seatWidthMm = $maxRowUnits > 0
            ? min(55.0, max(18.0, ($availableWidthMm - $maxDesksInRow * $deskGapMm) / $maxRowUnits - $cellOverheadMm))
            : 25.0;

        $seatHeightMm = $rowCount > 0
            ? min(42.0, max(14.0, ($availableHeightMm - max(0, $rowCount - 1) * $rowGapMm) / $rowCount - $cellOverheadMm))
            : 18.0;

        // Left and right swap along with everything else in the 180° turn;
        // the desk itself moves from above the grid to below it (see the
        // view), so "center" is the only position unaffected either way.
        $teacherDeskPosition = $this->printTeacherView
            ? match ($this->teacherDeskPosition) {
                'left' => 'right',
                'right' => 'left',
                default => $this->teacherDeskPosition,
            }
        : $this->teacherDeskPosition;

        $filename = 'plan-de-classe-'.str($schoolClass->name)->slug().'-'.($application->effective_date?->format('Y-m-d') ?? 'sans-date').'.pdf';

        $pdf = Pdf::loadView('pdf.seating-chart', [
            'schoolClass' => $schoolClass,
            'application' => $application,
            'teacherDeskPosition' => $teacherDeskPosition,
            'printTeacherView' => $this->printTeacherView,
            'rowsOfDesks' => $rowsOfDesks,
            'seatWidthMm' => $seatWidthMm,
            'seatHeightMm' => $seatHeightMm,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    public function clearSeats(): void
    {
        $application = $this->resolveApplication();

        if (! $application) {
            return;
        }

        SeatingPlanSeat::query()
            ->where('seating_plan_application_id', $application->id)
            ->where('is_locked', false)
            ->where('is_blocked', false)
            ->delete();

        $this->selectedStudentId = null;
    }
}
