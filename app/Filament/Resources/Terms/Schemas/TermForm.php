<?php

namespace App\Filament\Resources\Terms\Schemas;

use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TermForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Nom')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('Trimestre 1'),

                TextInput::make('school_year')
                    ->label('Année scolaire')
                    ->required()
                    ->maxLength(9)
                    ->default(fn () => SchoolClass::currentSchoolYear()),

                Select::make('parent_id')
                    ->label('Trimestre parent (si période)')
                    ->helperText('Laissez vide pour un trimestre. Une période appartient à un trimestre : ses notes comptent aussi dans la moyenne du trimestre, et elle a sa propre appréciation.')
                    ->options(
                        fn (?Term $record) => Term::query()
                            ->where('user_id', Auth::id())
                            ->whereNull('parent_id')
                            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                            ->orderBy('school_year')
                            ->orderBy('position')
                            ->pluck('label', 'id')
                    )
                    ->searchable(),

                DatePicker::make('starts_on')
                    ->label('Date de début')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                DatePicker::make('ends_on')
                    ->label('Date de fin')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('position')
                    ->label('Ordre')
                    ->numeric()
                    ->default(1)
                    ->required(),
            ]);
    }
}
