<?php

namespace App\Filament\Resources\LogbookEntries;

use App\Filament\Resources\LogbookEntries\Pages\CreateLogbookEntry;
use App\Filament\Resources\LogbookEntries\Pages\EditLogbookEntry;
use App\Filament\Resources\LogbookEntries\Pages\ListLogbookEntries;
use App\Filament\Resources\LogbookEntries\Schemas\LogbookEntryForm;
use App\Filament\Resources\LogbookEntries\Tables\LogbookEntriesTable;
use App\Models\LogbookEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LogbookEntryResource extends Resource
{
    protected static ?string $model = LogbookEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'séance';

    protected static ?string $pluralModelLabel = 'séances';

    protected static ?string $navigationLabel = 'Cahier de texte';

    protected static ?int $navigationSort = 28;

    public static function form(Schema $schema): Schema
    {
        return LogbookEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogbookEntriesTable::configure($table);
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
            'index' => ListLogbookEntries::route('/'),
            'create' => CreateLogbookEntry::route('/create'),
            'edit' => EditLogbookEntry::route('/{record}/edit'),
        ];
    }
}
