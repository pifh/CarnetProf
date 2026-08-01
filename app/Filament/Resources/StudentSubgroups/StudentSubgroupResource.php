<?php

namespace App\Filament\Resources\StudentSubgroups;

use App\Filament\Resources\StudentSubgroups\Pages\CreateStudentSubgroup;
use App\Filament\Resources\StudentSubgroups\Pages\EditStudentSubgroup;
use App\Filament\Resources\StudentSubgroups\Pages\ListStudentSubgroups;
use App\Filament\Resources\StudentSubgroups\Schemas\StudentSubgroupForm;
use App\Filament\Resources\StudentSubgroups\Tables\StudentSubgroupsTable;
use App\Models\StudentSubgroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StudentSubgroupResource extends Resource
{
    protected static ?string $model = StudentSubgroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'groupe';

    protected static ?string $pluralModelLabel = 'groupes';

    protected static ?string $navigationLabel = 'Groupes de travail';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return StudentSubgroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentSubgroupsTable::configure($table);
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
            'index' => ListStudentSubgroups::route('/'),
            'create' => CreateStudentSubgroup::route('/create'),
            'edit' => EditStudentSubgroup::route('/{record}/edit'),
        ];
    }
}
