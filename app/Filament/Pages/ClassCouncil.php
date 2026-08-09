<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ClassCouncil extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Conseil de classe';

    protected static ?string $title = 'Conseil de classe';

    protected static ?int $navigationSort = 33;

    protected string $view = 'filament.pages.class-council';

    public ?int $schoolClassId = null;

    public ?int $termId = null;

    public ?int $selectedStudentId = null;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->hasSubjects()
            ->orderBy('name')
            ->value('id');

        $this->termId = Term::query()
            ->where('user_id', Auth::id())
            ->orderBy('position')
            ->value('id');

        $this->selectedStudentId = $this->getStudentsProperty()->first()?->id;
    }

    public function updatedSchoolClassId(): void
    {
        $this->selectedStudentId = $this->getStudentsProperty()->first()?->id;
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
     * @return Collection<int, Term>
     */
    public function getTermsProperty(): Collection
    {
        return Term::hierarchicalForTeacher();
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

    public function selectStudent(int $studentId): void
    {
        $this->selectedStudentId = $studentId;
    }
}
