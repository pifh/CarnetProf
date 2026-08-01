<?php

namespace App\Filament\Resources\LogbookEntries\Pages;

use App\Filament\Resources\LogbookEntries\LogbookEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLogbookEntry extends CreateRecord
{
    protected static string $resource = LogbookEntryResource::class;
}
