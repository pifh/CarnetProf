<?php

namespace App\Filament\Resources\ProgressionSequences\Pages;

use App\Filament\Resources\ProgressionSequences\ProgressionSequenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgressionSequences extends ListRecords
{
    protected static string $resource = ProgressionSequenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
