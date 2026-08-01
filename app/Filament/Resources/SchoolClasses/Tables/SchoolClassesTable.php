<?php

namespace App\Filament\Resources\SchoolClasses\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SchoolClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label(''),
                TextColumn::make('name')
                    ->label('Classe')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Niveau')
                    ->sortable(),
                TextColumn::make('school_year')
                    ->label('Année scolaire')
                    ->sortable(),
                TextColumn::make('subjects.name')
                    ->label('Matières')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                IconColumn::make('is_archived')
                    ->label('Archivée')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_archived')
                    ->label('Archivée')
                    ->default(false),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('archive')
                    ->label('Archiver')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Cette classe et tous ses élèves actifs seront archivés.')
                    ->visible(fn ($record) => ! $record->is_archived)
                    ->action(function ($record) {
                        $record->update(['is_archived' => true, 'archived_at' => now()]);
                        $record->students()->where('is_archived', false)->update(['is_archived' => true, 'archived_at' => now()]);
                    }),
                Action::make('unarchive')
                    ->label('Désarchiver')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn ($record) => $record->is_archived)
                    ->action(fn ($record) => $record->update(['is_archived' => false, 'archived_at' => null])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
