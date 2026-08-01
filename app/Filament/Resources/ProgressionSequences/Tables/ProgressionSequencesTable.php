<?php

namespace App\Filament\Resources\ProgressionSequences\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProgressionSequencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn ($record) => $record->schoolClass?->color ?? 'gray')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('term.label')
                    ->label('Trimestre')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'not_started' => 'À faire',
                        'in_progress' => 'En cours',
                        'done' => 'Terminée',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'not_started' => 'gray',
                        'in_progress' => 'warning',
                        'done' => 'success',
                    }),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->relationship('schoolClass', 'name'),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'not_started' => 'À faire',
                        'in_progress' => 'En cours',
                        'done' => 'Terminée',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
