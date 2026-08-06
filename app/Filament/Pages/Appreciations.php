<?php

namespace App\Filament\Pages;

use App\Models\Appreciation;
use App\Models\AppreciationTemplate;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\AppreciationSuggester;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class Appreciations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Appréciations';

    protected static ?string $title = 'Appréciations';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.appreciations';

    public ?int $schoolClassId = null;

    public ?int $subjectId = null;

    public ?int $termId = null;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->orderBy('name')
            ->value('id');

        $this->termId = Term::query()
            ->where('user_id', Auth::id())
            ->orderBy('position')
            ->value('id');

        $this->syncSubjectId();
    }

    public function updatedSchoolClassId(): void
    {
        $this->syncSubjectId();
    }

    private function syncSubjectId(): void
    {
        $subjectIds = $this->getSubjectsProperty()->pluck('id');

        if (! $subjectIds->contains($this->subjectId)) {
            $this->subjectId = $subjectIds->first();
        }
    }

    /**
     * @return Collection<int, Subject>
     */
    public function getSubjectsProperty(): Collection
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        return $schoolClass?->subjects()->orderBy('name')->get() ?? collect();
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
     * @return Collection<int, Term>
     */
    public function getTermsProperty(): Collection
    {
        return Term::hierarchicalForTeacher();
    }

    /**
     * @return Collection<int, AppreciationTemplate>
     */
    public function getTemplatesProperty(): Collection
    {
        return AppreciationTemplate::query()
            ->where('user_id', Auth::id())
            ->orderBy('label')
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

        $appreciations = Appreciation::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $this->subjectId)
            ->where('term_id', $this->termId)
            ->get()
            ->keyBy('student_id');

        return $schoolClass->allStudents()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Student $student) use ($appreciations) {
                $student->appreciation = $appreciations->get($student->id);

                return $student;
            });
    }

    public function updateContent(int $studentId, ?string $value): void
    {
        $appreciation = $this->findOrNewAppreciation($studentId);

        if (! $appreciation) {
            return;
        }

        $appreciation->content = trim((string) $value) ?: null;
        $appreciation->save();
    }

    public function toggleDraft(int $studentId): void
    {
        $appreciation = $this->findAppreciation($studentId);

        if (! $appreciation) {
            return;
        }

        $appreciation->is_draft = ! $appreciation->is_draft;
        $appreciation->save();
    }

    public function suggest(int $studentId): void
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
        $student = $this->getStudentsProperty()->firstWhere('id', $studentId);

        if (! $schoolClass || ! $student) {
            return;
        }

        $suggestion = app(AppreciationSuggester::class)->suggest($student, $schoolClass, $term, $subject);

        if ($suggestion === null) {
            return;
        }

        $appreciation = $this->findOrNewAppreciation($studentId);
        $appreciation?->fill(['content' => $suggestion]);
        $appreciation?->save();
    }

    public function applyTemplate(int $studentId, string $templateId): void
    {
        if ($templateId === '') {
            return;
        }

        $template = $this->getTemplatesProperty()->firstWhere('id', (int) $templateId);

        if (! $template) {
            return;
        }

        $appreciation = $this->findOrNewAppreciation($studentId);
        $appreciation?->fill(['content' => $template->content]);
        $appreciation?->save();
    }

    private function findAppreciation(int $studentId): ?Appreciation
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return null;
        }

        return Appreciation::query()
            ->where('student_id', $studentId)
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $this->subjectId)
            ->where('term_id', $this->termId)
            ->first();
    }

    private function findOrNewAppreciation(int $studentId): ?Appreciation
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return null;
        }

        $appreciation = Appreciation::query()->firstOrNew([
            'student_id' => $studentId,
            'school_class_id' => $schoolClass->id,
            'subject_id' => $this->subjectId,
            'term_id' => $this->termId,
        ]);
        $appreciation->user_id = Auth::id();

        return $appreciation;
    }
}
