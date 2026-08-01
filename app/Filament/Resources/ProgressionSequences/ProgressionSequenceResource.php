<?php

namespace App\Filament\Resources\ProgressionSequences;

use App\Filament\Resources\ProgressionSequences\Pages\CreateProgressionSequence;
use App\Filament\Resources\ProgressionSequences\Pages\EditProgressionSequence;
use App\Filament\Resources\ProgressionSequences\Pages\ListProgressionSequences;
use App\Filament\Resources\ProgressionSequences\Schemas\ProgressionSequenceForm;
use App\Filament\Resources\ProgressionSequences\Tables\ProgressionSequencesTable;
use App\Models\ProgressionSequence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProgressionSequenceResource extends Resource
{
    protected static ?string $model = ProgressionSequence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $modelLabel = 'séquence';

    protected static ?string $pluralModelLabel = 'séquences';

    protected static ?string $navigationLabel = 'Séquences';

    protected static ?int $navigationSort = 27;

    public static function form(Schema $schema): Schema
    {
        return ProgressionSequenceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgressionSequencesTable::configure($table);
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
            'index' => ListProgressionSequences::route('/'),
            'create' => CreateProgressionSequence::route('/create'),
            'edit' => EditProgressionSequence::route('/{record}/edit'),
        ];
    }
}
