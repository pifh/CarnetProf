<?php

namespace App\Filament\Resources\StudentEvents\Pages;

use App\Filament\Resources\StudentEvents\StudentEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentEvents extends ListRecords
{
    protected static string $resource = StudentEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
