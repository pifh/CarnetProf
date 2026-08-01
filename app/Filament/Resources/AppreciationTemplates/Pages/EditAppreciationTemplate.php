<?php

namespace App\Filament\Resources\AppreciationTemplates\Pages;

use App\Filament\Resources\AppreciationTemplates\AppreciationTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAppreciationTemplate extends EditRecord
{
    protected static string $resource = AppreciationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
