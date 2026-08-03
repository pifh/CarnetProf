<?php

namespace App\Filament\Pages;

use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\StudentSubgroup;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class EvaluationGrades extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.evaluation-grades';

    public Evaluation $evaluation;

    public static function getRoutePath(Panel $panel): string
    {
        return '/evaluations/{evaluation}/grades';
    }

    public function mount(Evaluation $evaluation): void
    {
        abort_unless($evaluation->user_id === Auth::id(), 403);

        $this->evaluation = $evaluation;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Notes — '.$this->evaluation->title;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudentsProperty(): Collection
    {
        $grades = $this->evaluation->grades()->get()->keyBy('student_id');

        return $this->evaluation->schoolClass->students()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Student $student) use ($grades) {
                $student->grade = $grades->get($student->id);

                return $student;
            });
    }

    /**
     * @return SupportCollection<int, array{id: int, name: string, students: SupportCollection<int, Student>}>
     */
    public function getGroupsProperty(): SupportCollection
    {
        $generationId = $this->evaluation->groupGeneration?->id;

        if (! $generationId) {
            return collect();
        }

        $students = $this->getStudentsProperty()->keyBy('id');

        return StudentSubgroup::query()
            ->where('group_generation_id', $generationId)
            ->orderBy('name')
            ->get()
            ->map(fn (StudentSubgroup $subgroup) => [
                'id' => $subgroup->id,
                'name' => $subgroup->name,
                'students' => $subgroup->students()->pluck('students.id')
                    ->map(fn (int $id) => $students->get($id))
                    ->filter()
                    ->values(),
            ]);
    }

    /**
     * @return SupportCollection<int, Student>
     */
    public function getUngroupedStudentsProperty(): SupportCollection
    {
        if ($this->groups->isEmpty()) {
            return collect();
        }

        $groupedIds = $this->groups->flatMap(fn (array $group) => $group['students']->pluck('id'));

        return $this->getStudentsProperty()->reject(fn (Student $student) => $groupedIds->contains($student->id));
    }

    public function getClassAverage(): ?string
    {
        $graded = $this->getStudentsProperty()
            ->pluck('grade')
            ->filter(fn (?Grade $grade) => $grade?->status === 'graded' && $grade->score !== null);

        if ($graded->isEmpty()) {
            return null;
        }

        $average = $graded->avg(fn (Grade $grade) => ((float) $grade->score / (float) $this->evaluation->max_score) * 20);

        return number_format($average, 2);
    }

    public function updateScore(int $studentId, ?string $value): void
    {
        $this->applyScore($studentId, $this->parseScore($value));
    }

    /**
     * Copies one score to every member of the group — the fast path for
     * grading a shared activity. Individual scores can still be tweaked
     * afterwards via updateScore() for a single student.
     */
    public function updateGroupScore(int $subgroupId, ?string $value): void
    {
        $group = $this->groups->firstWhere('id', $subgroupId);

        if (! $group) {
            return;
        }

        $score = $this->parseScore($value);

        foreach ($group['students'] as $student) {
            $this->applyScore($student->id, $score);
        }
    }

    private function parseScore(?string $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return max(0, min((float) str_replace(',', '.', $value), (float) $this->evaluation->max_score));
    }

    private function applyScore(int $studentId, ?float $score): void
    {
        $grade = Grade::query()->firstOrNew([
            'evaluation_id' => $this->evaluation->id,
            'student_id' => $studentId,
        ]);
        $grade->user_id = Auth::id();
        $grade->score = $score;
        $grade->status = $score !== null ? 'graded' : 'not_graded';
        $grade->save();
    }

    public function setStatus(int $studentId, string $status): void
    {
        $grade = Grade::query()->firstOrNew([
            'evaluation_id' => $this->evaluation->id,
            'student_id' => $studentId,
        ]);
        $grade->user_id = Auth::id();
        $grade->status = $status;
        $grade->score = null;
        $grade->save();
    }

    public function exportCsv()
    {
        $rows = $this->getStudentsProperty();

        $statusLabels = [
            'graded' => 'Noté',
            'not_graded' => 'Non noté',
            'absent' => 'Absent',
            'exempted' => 'Dispensé',
            'to_retake' => 'À rattraper',
        ];

        $filename = 'notes-'.str($this->evaluation->title)->slug().'.csv';

        return Response::streamDownload(function () use ($rows, $statusLabels) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nom', 'Prénom', 'Note', 'Statut']);

            foreach ($rows as $student) {
                fputcsv($handle, [
                    $student->last_name,
                    $student->first_name,
                    $student->grade?->score ?? '',
                    $statusLabels[$student->grade?->status ?? 'not_graded'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
