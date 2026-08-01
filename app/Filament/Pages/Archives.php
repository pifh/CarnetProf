<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Archives extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Archives';

    protected static ?string $title = 'Archives';

    protected static ?int $navigationSort = 85;

    protected string $view = 'filament.pages.archives';

    /**
     * @return Collection<int, SchoolClass>
     */
    public function getArchivedClassesProperty(): Collection
    {
        return SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', true)
            ->withCount('students')
            ->orderByDesc('archived_at')
            ->get();
    }

    /**
     * Students archived on their own, while their class stays active
     * (e.g. moved away mid-year) — distinct from a full class archive.
     *
     * @return Collection<int, Student>
     */
    public function getArchivedStudentsProperty(): Collection
    {
        return Student::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', true)
            ->whereHas('schoolClass', fn ($query) => $query->where('is_archived', false))
            ->with('schoolClass')
            ->orderByDesc('archived_at')
            ->get();
    }

    public function unarchiveClass(int $schoolClassId): void
    {
        SchoolClass::query()
            ->where('id', $schoolClassId)
            ->update(['is_archived' => false, 'archived_at' => null]);
    }

    public function unarchiveStudent(int $studentId): void
    {
        Student::query()
            ->where('id', $studentId)
            ->update(['is_archived' => false, 'archived_at' => null]);
    }
}
