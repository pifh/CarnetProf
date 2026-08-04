<?php

namespace App\Filament\Resources\StudentEvents\Pages;

use App\Filament\Resources\StudentEvents\StudentEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudentEvent extends EditRecord
{
    protected static string $resource = StudentEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['all_day'] ?? false) {
            $data['ends_at'] = null;
        }

        return $data;
    }
}
