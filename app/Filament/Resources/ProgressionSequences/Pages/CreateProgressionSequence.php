<?php

namespace App\Filament\Resources\ProgressionSequences\Pages;

use App\Filament\Resources\ProgressionSequences\ProgressionSequenceResource;
use App\Models\ProgressionSequence;
use Filament\Resources\Pages\CreateRecord;

class CreateProgressionSequence extends CreateRecord
{
    protected static string $resource = ProgressionSequenceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['position'] = ProgressionSequence::query()
            ->where('school_class_id', $data['school_class_id'])
            ->max('position') + 1;

        return $data;
    }
}
