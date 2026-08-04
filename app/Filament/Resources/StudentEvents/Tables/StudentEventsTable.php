<?php

namespace App\Filament\Resources\StudentEvents\Tables;

use App\Models\SchoolClass;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StudentEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->formatStateUsing(fn ($state, $record) => $state->format($record->all_day ? 'd/m/Y' : 'd/m/Y H:i'))
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('student.full_name')
                    ->label('Élève')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('student.schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn ($record) => $record->student?->schoolClass?->color ?? 'gray'),
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
                SelectFilter::make('student_id')
                    ->label('Classe')
                    ->options(fn () => SchoolClass::query()->where('user_id', Auth::id())->where('is_archived', false)->orderBy('name')->pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return $query->whereHas('student', fn ($q) => $q->where('school_class_id', $data['value']));
                    }),
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
