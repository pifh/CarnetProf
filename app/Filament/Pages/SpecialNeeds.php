<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class SpecialNeeds extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Besoins particuliers';

    protected static ?string $title = 'Besoins particuliers';

    protected static ?int $navigationSort = 26;

    protected string $view = 'filament.pages.special-needs';

    #[Url]
    public ?int $schoolClassId = null;

    public function mount(): void
    {
        if ($this->schoolClassId) {
            return;
        }

        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->hasSubjects()
            ->orderBy('name')
            ->value('id');
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
            ->whereNotNull('special_needs')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->filter(fn (Student $student) => ! empty($student->special_needs))
            ->values();
    }
}
