<?php

namespace App\Filament\Resources\LogbookEntries\Schemas;

use App\Models\EcoleDirecteEvent;
use App\Models\LogbookEntry;
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
                        modifyQueryUsing: fn ($query) => $query->where('user_id', Auth::id())->where('is_archived', false)->hasSubjects(),
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
                    ->live()
                    ->searchable(),

                DatePicker::make('date')
                    ->label('Date de la séance')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now())
                    ->required(),

                Select::make('status')
                    ->label('Statut')
                    ->options([
                        LogbookEntry::STATUS_PLANNED => 'Prévue',
                        LogbookEntry::STATUS_DONE => 'Faite',
                    ])
                    ->default(LogbookEntry::STATUS_DONE)
                    ->live()
                    ->required(),

                Select::make('ecole_directe_event_id')
                    ->label('Séance de l\'emploi du temps (École-Directe)')
                    ->placeholder('Aucune')
                    ->options(function (?LogbookEntry $record) {
                        return EcoleDirecteEvent::query()
                            ->where('user_id', Auth::id())
                            ->whereBetween('starts_at', [now()->subDays(60), now()->addDays(60)])
                            // Already-linked séances aren't offered again — except
                            // the one already linked to this record, so editing
                            // doesn't hide its own current selection.
                            ->where(function ($query) use ($record) {
                                $query->whereDoesntHave('logbookEntry')
                                    ->when($record?->ecole_directe_event_id, fn ($q, $id) => $q->orWhere('id', $id));
                            })
                            ->orderBy('starts_at')
                            ->get()
                            ->mapWithKeys(fn (EcoleDirecteEvent $event) => [
                                $event->id => $event->starts_at->format('d/m/Y H:i').' — '.$event->title,
                            ]);
                    })
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('date', EcoleDirecteEvent::query()->find($state)?->starts_at?->toDateString());
                        }
                    })
                    ->columnSpanFull(),

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
                            ->where('subject_id', $get('subject_id'))
                            ->pluck('title', 'id') ?? [];
                    })
                    ->searchable()
                    ->columnSpanFull(),

                Textarea::make('content')
                    ->label('Contenu de la séance')
                    ->required(fn (callable $get) => $get('status') === LogbookEntry::STATUS_DONE)
                    ->rows(4)
                    ->columnSpanFull(),

                Textarea::make('homework')
                    ->label('Travail à faire')
                    ->helperText('Écrit ici, sur la séance à laquelle ce travail est à faire — la date de la séance sert de date de rendu.')
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
