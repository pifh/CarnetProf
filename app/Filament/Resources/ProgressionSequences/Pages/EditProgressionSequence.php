<?php

namespace App\Filament\Resources\ProgressionSequences\Pages;

use App\Filament\Resources\ProgressionSequences\ProgressionSequenceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgressionSequence extends EditRecord
{
    protected static string $resource = ProgressionSequenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
