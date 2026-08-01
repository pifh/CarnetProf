<?php

namespace App\Filament\Resources\LogbookEntries\Pages;

use App\Filament\Resources\LogbookEntries\LogbookEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLogbookEntry extends EditRecord
{
    protected static string $resource = LogbookEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
