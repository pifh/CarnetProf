<?php

namespace App\Filament\Resources\Students\Tables;

use App\Models\SchoolClass;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->action(fn (Student $record, $livewire) => $livewire->dispatch('open-student-preview', studentId: $record->id)),
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable()
                    ->action(fn (Student $record, $livewire) => $livewire->dispatch('open-student-preview', studentId: $record->id)),
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn ($record) => $record->schoolClass?->color ?? 'gray')
                    ->sortable(),
                TextColumn::make('birth_date')
                    ->label('Naissance')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('is_delegate')
                    ->label('Délégué')
                    ->boolean(),
                IconColumn::make('is_archived')
                    ->label('Archivé(e)')
                    ->boolean(),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->options(fn () => SchoolClass::query()->where('user_id', Auth::id())->hasSubjects()->pluck('name', 'id')),
                TernaryFilter::make('is_archived')
                    ->label('Archivé(e)')
                    ->default(false),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('archive')
                    ->label('Archiver')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => ! $record->is_archived)
                    ->action(fn ($record) => $record->update(['is_archived' => true, 'archived_at' => now()])),
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
