<?php

namespace App\Filament\Resources\Evaluations\Schemas;

use App\Models\SchoolClass;
use App\Models\Term;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EvaluationForm
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

                Select::make('subject_id')
                    ->label('Matière')
                    ->options(fn (callable $get) => SchoolClass::query()->find($get('school_class_id'))?->subjects()->pluck('name', 'subjects.id') ?? [])
                    ->visible(fn (callable $get) => SchoolClass::query()->find($get('school_class_id'))?->subjects()->exists() ?? false)
                    ->required(fn (callable $get) => SchoolClass::query()->find($get('school_class_id'))?->subjects()->exists() ?? false)
                    ->searchable(),

                Select::make('term_id')
                    ->label('Trimestre')
                    ->relationship(
                        name: 'term',
                        titleAttribute: 'label',
                        modifyQueryUsing: fn ($query) => $query->where('user_id', Auth::id())->orderBy('position'),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
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
                        TextInput::make('position')
                            ->label('Ordre')
                            ->numeric()
                            ->default(1)
                            ->required(),
                    ])
                    ->createOptionAction(fn ($action) => $action->modalHeading('Nouveau trimestre'))
                    ->createOptionUsing(function (array $data) {
                        return Term::create($data)->getKey();
                    }),

                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Contrôle chapitre 3'),

                DatePicker::make('exam_date')
                    ->label('Date')
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                TextInput::make('coefficient')
                    ->label('Coefficient')
                    ->numeric()
                    ->default(1)
                    ->step(0.01)
                    ->minValue(0.1)
                    ->required(),

                TextInput::make('max_score')
                    ->label('Barème')
                    ->numeric()
                    ->default(20)
                    ->step(0.01)
                    ->minValue(1)
                    ->required(),
            ]);
    }
}
