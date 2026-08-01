<?php

namespace App\Filament\Resources\AppreciationTemplates;

use App\Filament\Resources\AppreciationTemplates\Pages\CreateAppreciationTemplate;
use App\Filament\Resources\AppreciationTemplates\Pages\EditAppreciationTemplate;
use App\Filament\Resources\AppreciationTemplates\Pages\ListAppreciationTemplates;
use App\Filament\Resources\AppreciationTemplates\Schemas\AppreciationTemplateForm;
use App\Filament\Resources\AppreciationTemplates\Tables\AppreciationTemplatesTable;
use App\Models\AppreciationTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AppreciationTemplateResource extends Resource
{
    protected static ?string $model = AppreciationTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'modèle d\'appréciation';

    protected static ?string $pluralModelLabel = 'modèles d\'appréciations';

    protected static ?string $navigationLabel = 'Modèles d\'appréciations';

    protected static ?int $navigationSort = 31;

    public static function form(Schema $schema): Schema
    {
        return AppreciationTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppreciationTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppreciationTemplates::route('/'),
            'create' => CreateAppreciationTemplate::route('/create'),
            'edit' => EditAppreciationTemplate::route('/{record}/edit'),
        ];
    }
}
