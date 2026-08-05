<?php

namespace App\Services;

use App\Models\DisciplineEntry;
use App\Models\DisciplineReset;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Counts forgotten-material / undone-homework / talking / discipline
 * incidents per student like a car's dashboard: a lifetime "total" (every
 * DisciplineEntry ever logged, never touched) and a "trip" since the last
 * reset (DisciplineReset just moves the boundary forward — it never
 * deletes history, so a student's full list of dates per category stays
 * available in their file).
 */
class DisciplineTracker
{
    public function logEntry(Student $student, string $category): DisciplineEntry
    {
        return DisciplineEntry::create([
            'student_id' => $student->id,
            'category' => $category,
            'occurred_at' => now()->toDateString(),
        ]);
    }

    public function resetStudent(Student $student, string $category): void
    {
        $lastEntryId = DisciplineEntry::query()
            ->where('student_id', $student->id)
            ->where('category', $category)
            ->max('id');

        DisciplineReset::updateOrCreate(
            ['student_id' => $student->id, 'category' => $category],
            ['last_entry_id' => $lastEntryId],
        );
    }

    public function resetClass(SchoolClass $schoolClass, string $category): void
    {
        $schoolClass->students()->where('is_archived', false)->get()
            ->each(fn (Student $student) => $this->resetStudent($student, $category));
    }

    /**
     * @return Collection<string, array{total: int, trip: int}> keyed by "studentId|category"
     */
    public function countsForClass(SchoolClass $schoolClass): Collection
    {
        $studentIds = $schoolClass->students()->where('is_archived', false)->pluck('id');

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $entries = DisciplineEntry::query()
            ->whereIn('student_id', $studentIds)
            ->get(['id', 'student_id', 'category'])
            ->groupBy(fn (DisciplineEntry $entry) => $entry->student_id.'|'.$entry->category);

        $resets = DisciplineReset::query()
            ->whereIn('student_id', $studentIds)
            ->get(['student_id', 'category', 'last_entry_id'])
            ->keyBy(fn (DisciplineReset $reset) => $reset->student_id.'|'.$reset->category);

        return $entries->map(function (Collection $group, string $key) use ($resets) {
            $lastEntryId = $resets->get($key)?->last_entry_id;

            return [
                'total' => $group->count(),
                'trip' => $lastEntryId ? $group->where('id', '>', $lastEntryId)->count() : $group->count(),
            ];
        });
    }

    /**
     * @return array{total: int, trip: int}
     */
    public function countsForStudent(Student $student, string $category): array
    {
        $total = DisciplineEntry::query()->where('student_id', $student->id)->where('category', $category)->count();

        $lastEntryId = DisciplineReset::query()
            ->where('student_id', $student->id)
            ->where('category', $category)
            ->value('last_entry_id');

        $trip = $lastEntryId
            ? DisciplineEntry::query()->where('student_id', $student->id)->where('category', $category)->where('id', '>', $lastEntryId)->count()
            : $total;

        return ['total' => $total, 'trip' => $trip];
    }
}
