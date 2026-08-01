<?php

namespace App\Filament\Pages;

use App\Models\SchoolClass;
use App\Models\Student;
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

    protected static ?int $navigationSort = 26;

    protected string $view = 'filament.pages.averages';

    public ?int $schoolClassId = null;

    public ?int $termId = null;

    public bool $showRanking = false;

    public function mount(): void
    {
        $this->schoolClassId = SchoolClass::query()
            ->where('user_id', Auth::id())
            ->where('is_archived', false)
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
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Term>
     */
    public function getTermsProperty(): Collection
    {
        return Term::query()
            ->where('user_id', Auth::id())
            ->orderBy('position')
            ->get();
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
        $calculator = app(GradeCalculator::class);

        $rows = $schoolClass->students()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($student) => [
                'student' => $student,
                'average' => $calculator->studentAverage($student, $schoolClass, $term),
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
        $average = app(GradeCalculator::class)->classAverage($schoolClass, $term);

        return $average !== null ? number_format($average, 2) : null;
    }

    public function downloadBulletin(int $studentId)
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->getTermsProperty()->firstWhere('id', $this->termId);
        $student = $schoolClass?->students()->find($studentId);

        if (! $schoolClass || ! $term || ! $student) {
            return null;
        }

        $bulletin = app(BulletinGenerator::class)->build($student, $schoolClass, $term);

        $filename = 'bulletin-'.str($student->last_name.'-'.$student->first_name)->slug().'.pdf';
        $pdf = Pdf::loadView('pdf.bulletin', ['bulletins' => [$bulletin]]);

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, ['Content-Type' => 'application/pdf']);
    }

    public function downloadClassBulletins()
    {
        $schoolClass = $this->getSchoolClassesProperty()->firstWhere('id', $this->schoolClassId);
        $term = $this->getTermsProperty()->firstWhere('id', $this->termId);

        if (! $schoolClass || ! $term) {
            return null;
        }

        $generator = app(BulletinGenerator::class);

        $bulletins = $schoolClass->students()
            ->where('is_archived', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Student $student) => $generator->build($student, $schoolClass, $term));

        $filename = 'bulletins-'.str($schoolClass->name.'-'.$term->label)->slug().'.pdf';
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
