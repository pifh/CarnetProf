<?php

namespace App\Filament\Resources\AppreciationTemplates\Pages;

use App\Filament\Resources\AppreciationTemplates\AppreciationTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppreciationTemplates extends ListRecords
{
    protected static string $resource = AppreciationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
