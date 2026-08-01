<?php

namespace App\Filament\Resources\ProgressionSequences\Schemas;

use App\Models\SchoolClass;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ProgressionSequenceForm
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
                    ->placeholder('Non assignée'),

                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('ex. Chapitre 3 : Les fractions')
                    ->columnSpanFull(),

                Select::make('status')
                    ->label('Statut')
                    ->options([
                        'not_started' => 'À faire',
                        'in_progress' => 'En cours',
                        'done' => 'Terminée',
                    ])
                    ->default('not_started')
                    ->required(),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
