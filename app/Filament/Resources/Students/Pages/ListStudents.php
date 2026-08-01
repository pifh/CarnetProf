<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Pages\ImportStudents;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Response;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Importer')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(fn () => ImportStudents::getUrl()),
            Action::make('export')
                ->label('Exporter CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn () => static::exportCsv()),
            CreateAction::make(),
        ];
    }

    protected static function exportCsv()
    {
        $students = Student::query()
            ->with('schoolClass')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Response::streamDownload(function () use ($students) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Nom', 'Prénom', 'Classe', 'Niveau', 'Naissance', 'Délégué', 'Archivé']);

            foreach ($students as $student) {
                fputcsv($handle, [
                    $student->last_name,
                    $student->first_name,
                    $student->schoolClass?->name,
                    $student->schoolClass?->level,
                    $student->birth_date?->format('d/m/Y'),
                    $student->is_delegate ? 'Oui' : 'Non',
                    $student->is_archived ? 'Oui' : 'Non',
                ]);
            }

            fclose($handle);
        }, 'eleves.csv', ['Content-Type' => 'text/csv']);
    }
}
