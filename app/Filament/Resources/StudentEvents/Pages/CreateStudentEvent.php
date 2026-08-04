<?php

namespace App\Filament\Resources\StudentEvents\Pages;

use App\Filament\Resources\StudentEvents\StudentEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudentEvent extends CreateRecord
{
    protected static string $resource = StudentEventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['all_day'] ?? false) {
            $data['ends_at'] = null;
        }

        return $data;
    }
}
