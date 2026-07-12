<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Pages\ImportStudents;
use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

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
            CreateAction::make(),
        ];
    }
}
