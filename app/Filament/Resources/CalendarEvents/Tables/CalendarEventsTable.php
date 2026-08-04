<?php

namespace App\Filament\Resources\CalendarEvents\Tables;

use App\Models\CalendarEvent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CalendarEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => CalendarEvent::typeLabels()[$state] ?? $state),
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->formatStateUsing(fn ($state, $record) => $state->format($record->all_day ? 'd/m/Y' : 'd/m/Y H:i'))
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->formatStateUsing(fn ($state, $record) => $state?->format($record->all_day ? 'd/m/Y' : 'd/m/Y H:i'))
                    ->placeholder('—'),
                TextColumn::make('attachments_count')
                    ->label('Fichiers')
                    ->counts('attachments')
                    ->badge(),
                TextColumn::make('notes')
                    ->label('Compte rendu')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(CalendarEvent::typeLabels()),
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
