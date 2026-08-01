<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class GroupGenerator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Générateur de groupes';

    protected static ?string $title = 'Générateur de groupes';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.group-generator';

    public ?int $schoolClassId = null;

    public string $mode = 'count';

    public int $groupCount = 4;

    public int $groupSize = 4;

    /** @var array<int, int> */
    public array $excludedStudentIds = [];

    /** @var array<int, array<int, int>> */
    public array $generatedGroups = [];

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->value('id');
    }

    public function updatedSchoolClassId(): void
    {
        $this->excludedStudentIds = [];
        $this->generatedGroups = [];
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

    public function toggleExcluded(int $studentId): void
    {
        if (in_array($studentId, $this->excludedStudentIds, true)) {
            $this->excludedStudentIds = array_values(array_diff($this->excludedStudentIds, [$studentId]));
        } else {
            $this->excludedStudentIds[] = $studentId;
        }
    }

    public function generate(): void
    {
        $pool = $this->getStudentsProperty()
            ->reject(fn (Student $student) => in_array($student->id, $this->excludedStudentIds, true))
            ->shuffle()
            ->values();

        if ($pool->isEmpty()) {
            $this->generatedGroups = [];

            return;
        }

        $groupCount = $this->mode === 'size'
            ? max(1, (int) ceil($pool->count() / max(1, $this->groupSize)))
            : max(1, min($this->groupCount, $pool->count()));

        $groups = array_fill(0, $groupCount, []);

        foreach ($pool as $index => $student) {
            $groups[$index % $groupCount][] = $student->id;
        }

        $this->generatedGroups = $groups;
    }

    /**
     * @return array<int, array{name: string, students: Collection<int, Student>}>
     */
    public function getGeneratedGroupsDisplayProperty(): array
    {
        $students = $this->getStudentsProperty();

        return collect($this->generatedGroups)
            ->map(fn (array $studentIds, int $index) => [
                'name' => 'Groupe '.($index + 1),
                'students' => collect($studentIds)->map(fn (int $id) => $students->firstWhere('id', $id))->filter()->values(),
            ])
            ->values()
            ->all();
    }

    public function save(): void
    {
        if (empty($this->generatedGroups) || ! $this->schoolClassId) {
            return;
        }

        StudentSubgroup::query()
            ->where('school_class_id', $this->schoolClassId)
            ->delete();

        foreach ($this->generatedGroups as $index => $studentIds) {
            $group = new StudentSubgroup(['school_class_id' => $this->schoolClassId, 'name' => 'Groupe '.($index + 1)]);
            $group->user_id = Auth::id();
            $group->save();
            $group->students()->sync($studentIds);
        }

        Notification::make()
            ->title('Groupes enregistrés')
            ->success()
            ->send();
    }
}
