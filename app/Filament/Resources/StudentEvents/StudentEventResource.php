<?php

namespace App\Filament\Resources\StudentEvents;

use App\Filament\Resources\StudentEvents\Pages\CreateStudentEvent;
use App\Filament\Resources\StudentEvents\Pages\EditStudentEvent;
use App\Filament\Resources\StudentEvents\Pages\ListStudentEvents;
use App\Filament\Resources\StudentEvents\Schemas\StudentEventForm;
use App\Filament\Resources\StudentEvents\Tables\StudentEventsTable;
use App\Models\StudentEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentEventResource extends Resource
{
    protected static ?string $model = StudentEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'événement';

    protected static ?string $pluralModelLabel = 'événements';

    protected static ?string $navigationLabel = 'Événements élèves';

    protected static ?int $navigationSort = 28;

    public static function form(Schema $schema): Schema
    {
        return StudentEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentEventsTable::configure($table);
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
            'index' => ListStudentEvents::route('/'),
            'create' => CreateStudentEvent::route('/create'),
            'edit' => EditStudentEvent::route('/{record}/edit'),
        ];
    }
}
