<?php

namespace App\Filament\Resources\StudentSubgroups\Pages;

use App\Filament\Resources\StudentSubgroups\StudentSubgroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudentSubgroups extends ListRecords
{
    protected static string $resource = StudentSubgroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
