<?php

namespace App\Filament\Resources\LogbookEntries\Pages;

use App\Filament\Resources\LogbookEntries\LogbookEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLogbookEntries extends ListRecords
{
    protected static string $resource = LogbookEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
