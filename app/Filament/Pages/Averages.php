<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Services\BulletinGenerator;
use App\Services\GradeCalculator;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class Averages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Moyennes';

    protected static ?string $title = 'Moyennes';

    protected static ?int $navigationSort = 27;

    protected string $view = 'filament.pages.averages';

    public ?int $schoolClassId = null;

    public ?int $subjectId = null;

    public ?int $termId = null;

    public bool $showRanking = false;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
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
     * @return Collection<int, array{student: Student, average: ?float}>
     */
    public function getRowsProperty(): Collection
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return collect();
        }

        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
        $calculator = app(GradeCalculator::class);

        $rows = $schoolClass->allStudents()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($student) => [
                'student' => $student,
                'average' => $calculator->studentAverage($student, $schoolClass, $term, $subject),
            ]);

        if ($this->showRanking) {
            $rows = $rows->sortByDesc('average')->values();
        }

        return $rows;
    }

    public function getClassAverage(): ?string
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);

        if (! $schoolClass) {
            return null;
        }

        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
        $average = app(GradeCalculator::class)->classAverage($schoolClass, $term, $subject);

        return $average !== null ? number_format($average, 2) : null;
    }

    public function downloadBulletin(int $studentId)
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;
        $student = $schoolClass?->allStudents()->find($studentId);

        if (! $schoolClass || ! $student) {
            return null;
        }

        $bulletin = app(BulletinGenerator::class)->build($student, $schoolClass, $term, $subject);

        $filename = 'bulletin-'.str($student->last_name.'-'.$student->first_name)->slug().'.pdf';
        $pdf = Pdf::loadView('pdf.bulletin', ['bulletins' => [$bulletin]]);

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    public function downloadClassBulletins()
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;
        $subject = $this->subjectId ? $this->getSubjectsProperty()->firstWhere('id', $this->subjectId) : null;

        if (! $schoolClass) {
            return null;
        }

        $generator = app(BulletinGenerator::class);

        $bulletins = $schoolClass->allStudents()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Student $student) => $generator->build($student, $schoolClass, $term, $subject));

        $filename = 'bulletins-'.str($schoolClass->name.'-'.($term?->label ?? 'Année complète'))->slug().'.pdf';
        $pdf = Pdf::loadView('pdf.bulletin', ['bulletins' => $bulletins]);

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    public function exportCsv()
    {
        $rows = $this->getRowsProperty();
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->termId ? $this->getTermsProperty()->firstWhere('id', $this->termId) : null;

        $filename = 'moyennes-'.str(($schoolClass?->name ?? 'classe').'-'.($term?->label ?? 'annee'))->slug().'.csv';

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nom', 'Prénom', 'Moyenne']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['student']->last_name,
                    $row['student']->first_name,
                    $row['average'] !== null ? number_format($row['average'], 2) : '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
