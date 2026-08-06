<?php

namespace App\Filament\Resources\Terms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TermsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('school_year')
                    ->label('Année scolaire')
                    ->sortable(),
                TextColumn::make('parent.label')
                    ->label('Trimestre parent')
                    ->placeholder('—'),
                TextColumn::make('starts_on')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('ends_on')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('position')
                    ->label('Ordre')
                    ->sortable(),
            ])
            ->defaultSort('school_year', 'desc')
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
