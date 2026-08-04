<?php

namespace App\Filament\Resources\LogbookEntries\Tables;

use App\Models\Subject;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class LogbookEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'planned' ? 'Prévue' : 'Faite')
                    ->color(fn (string $state) => $state === 'planned' ? 'warning' : 'success'),
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn ($record) => $record->schoolClass?->color ?? 'gray')
                    ->sortable(),
                TextColumn::make('subject.name')
                    ->label('Matière')
                    ->placeholder('—'),
                TextColumn::make('content')
                    ->label('Contenu')
                    ->limit(60)
                    ->wrap(),
                IconColumn::make('homework')
                    ->label('Devoirs')
                    ->boolean()
                    ->state(fn ($record) => filled($record->homework)),
                TextColumn::make('homework_due_date')
                    ->label('À rendre le')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->relationship('schoolClass', 'name'),
                SelectFilter::make('subject_id')
                    ->label('Matière')
                    ->options(fn () => Subject::query()->where('user_id', Auth::id())->pluck('name', 'id')),
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
