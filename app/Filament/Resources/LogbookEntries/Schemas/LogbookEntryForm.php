<?php

namespace App\Filament\Resources\LogbookEntries\Schemas;

use App\Models\SchoolClass;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LogbookEntryForm
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

                DatePicker::make('date')
                    ->label('Date de la séance')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now())
                    ->required(),

                Select::make('progression_sequence_id')
                    ->label('Séquence liée')
                    ->placeholder('Aucune')
                    ->options(function (callable $get) {
                        $schoolClassId = $get('school_class_id');

                        if (! $schoolClassId) {
                            return [];
                        }

                        return SchoolClass::query()
                            ->find($schoolClassId)
                            ?->progressionSequences()
                            ->pluck('title', 'id') ?? [];
                    })
                    ->searchable()
                    ->columnSpanFull(),

                Textarea::make('content')
                    ->label('Contenu de la séance')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),

                Textarea::make('homework')
                    ->label('Travail à faire')
                    ->rows(3)
                    ->columnSpanFull(),

                DatePicker::make('homework_due_date')
                    ->label('À rendre pour le')
                    ->native(false)
                    ->displayFormat('d/m/Y'),
            ])
            ->columns(2);
    }
}
