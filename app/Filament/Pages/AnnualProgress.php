<?php

namespace App\Filament\Pages;

use App\Models\ProgressionSequence;
use App\Models\SchoolClass;
use App\Models\Subject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AnnualProgress extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Progression annuelle';

    protected static ?string $title = 'Progression annuelle';

    protected static ?int $navigationSort = 25;

    protected string $view = 'filament.pages.annual-progress';

    public ?int $schoolClassId = null;

    public ?int $subjectId = null;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
            ->hasSubjects()
            ->orderBy('name')
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
     * @return Collection<int, Subject>
     */
    public function getSubjectsProperty(): Collection
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        return $schoolClass?->subjects()->orderBy('name')->get() ?? collect();
    }

    /**
     * @return Collection<int, ProgressionSequence>
     */
    public function getSequencesProperty(): Collection
    {
        if (! $this->schoolClassId) {
            return collect();
        }

        return ProgressionSequence::query()
            ->where('school_class_id', $this->schoolClassId)
            ->where('subject_id', $this->subjectId)
            ->with('term')
            ->orderBy('position')
            ->get();
    }

    public function getProgressPercentProperty(): int
    {
        $sequences = $this->getSequencesProperty();

        if ($sequences->isEmpty()) {
            return 0;
        }

        return (int) round($sequences->where('status', 'done')->count() / $sequences->count() * 100);
    }

    /**
     * @return array{label: ?string, color: string}
     */
    public function getPacingProperty(): array
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $sequences = $this->getSequencesProperty();

        if (! $schoolClass || $sequences->isEmpty()) {
            return ['label' => null, 'color' => 'gray'];
        }

        $elapsed = $this->elapsedFraction($schoolClass->school_year);
        $done = $sequences->where('status', 'done')->count() / $sequences->count();
        $diff = $done - $elapsed;

        return match (true) {
            $diff >= 0.1 => ['label' => 'En avance', 'color' => 'success'],
            $diff <= -0.15 => ['label' => 'En retard', 'color' => 'danger'],
            default => ['label' => 'Dans les temps', 'color' => 'warning'],
        };
    }

    private function elapsedFraction(string $schoolYear): float
    {
        $startYear = (int) explode('-', $schoolYear)[0];

        $start = Carbon::create($startYear, 9, 1);
        $end = Carbon::create($startYear + 1, 7, 5);
        $now = Carbon::now();

        if ($now->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            return 1.0;
        }

        return $start->diffInDays($now) / $start->diffInDays($end);
    }

    public function toggleStatus(int $sequenceId): void
    {
        $sequence = ProgressionSequence::query()->find($sequenceId);

        if (! $sequence) {
            return;
        }

        $sequence->status = match ($sequence->status) {
            'not_started' => 'in_progress',
            'in_progress' => 'done',
            'done' => 'not_started',
        };

        $sequence->completed_at = $sequence->status === 'done' ? now()->toDateString() : null;
        $sequence->save();
    }
}
