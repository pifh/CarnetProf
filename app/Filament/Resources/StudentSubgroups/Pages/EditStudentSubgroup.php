<?php

namespace App\Filament\Resources\StudentSubgroups\Pages;

use App\Filament\Resources\StudentSubgroups\StudentSubgroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentSubgroup extends EditRecord
{
    protected static string $resource = StudentSubgroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
