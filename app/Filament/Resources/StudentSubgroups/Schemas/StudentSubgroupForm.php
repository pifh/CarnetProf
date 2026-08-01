<?php

namespace App\Filament\Resources\StudentSubgroups\Schemas;

use App\Models\Student;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StudentSubgroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_class_id')
                    ->label('Classe')
                    ->relationship(
                        name: 'schoolClass',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('user_id', Auth::id())->where('is_archived', false),
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),

                TextInput::make('name')
                    ->label('Nom du groupe')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('ex. Groupe A, Atelier lecture...'),

                ColorPicker::make('color')
                    ->label('Couleur')
                    ->default('#6b7280'),

                Select::make('students')
                    ->label('Élèves')
                    ->relationship(name: 'students', titleAttribute: 'first_name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Uniquement les élèves de la classe sélectionnée.')
                    ->options(function (callable $get) {
                        $schoolClassId = $get('school_class_id');

                        if (! $schoolClassId) {
                            return [];
                        }

                        return Student::query()
                            ->where('school_class_id', $schoolClassId)
                            ->where('is_archived', false)
                            ->orderBy('last_name')
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (Student $student) => [$student->id => $student->full_name]);
                    })
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
