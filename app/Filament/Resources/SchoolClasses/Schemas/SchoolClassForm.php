<?php

namespace App\Filament\Resources\SchoolClasses\Schemas;

use App\Models\SchoolClass;
use App\Models\Subject;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom de la classe')
                    ->required()
                    ->maxLength(255),

                TextInput::make('level')
                    ->label('Niveau')
                    ->maxLength(255)
                    ->placeholder('ex. 6e, Terminale, CM2...'),

                TextInput::make('school_year')
                    ->label('Année scolaire')
                    ->required()
                    ->maxLength(9)
                    ->default(fn () => SchoolClass::currentSchoolYear())
                    ->placeholder('2026-2027'),

                Select::make('subjects')
                    ->label('Matières')
                    ->relationship(
                        name: 'subjects',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->where('user_id', Auth::id()),
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Une classe partagée entre plusieurs matières (ex. Maths et Informatique) garde le même groupe d\'élèves, mais des notes, appréciations, progressions et cahiers de texte séparés par matière.')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nom de la matière')
                            ->required()
                            ->maxLength(255),
                        ColorPicker::make('color')
                            ->label('Couleur')
                            ->default('#6b7280'),
                    ])
                    ->createOptionAction(fn ($action) => $action->modalHeading('Nouvelle matière'))
                    ->createOptionUsing(function (array $data) {
                        return Subject::create($data)->getKey();
                    }),

                ColorPicker::make('color')
                    ->label('Couleur d\'identification')
                    ->default('#6b7280'),

                Textarea::make('notes')
                    ->label('Commentaires internes')
                    ->helperText('Visibles uniquement par vous.')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
