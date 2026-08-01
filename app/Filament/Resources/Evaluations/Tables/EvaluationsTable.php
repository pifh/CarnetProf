<?php

namespace App\Filament\Resources\Evaluations\Tables;

use App\Filament\Pages\EvaluationGrades;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EvaluationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Évaluation')
                    ->searchable(),
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn ($record) => $record->schoolClass?->color ?? 'gray'),
                TextColumn::make('subject.name')
                    ->label('Matière')
                    ->placeholder('—'),
                TextColumn::make('term.label')
                    ->label('Trimestre'),
                TextColumn::make('exam_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('coefficient')
                    ->label('Coef.'),
                TextColumn::make('max_score')
                    ->label('Barème'),
                TextColumn::make('class_average')
                    ->label('Moyenne classe')
                    ->getStateUsing(function ($record) {
                        $grades = $record->grades()->where('status', 'graded')->whereNotNull('score')->get();

                        if ($grades->isEmpty()) {
                            return '—';
                        }

                        $average = $grades->avg(fn ($grade) => ((float) $grade->score / (float) $record->max_score) * 20);

                        return number_format($average, 2).'/20';
                    }),
            ])
            ->defaultSort('exam_date', 'desc')
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Classe')
                    ->options(fn () => SchoolClass::query()->where('user_id', Auth::id())->pluck('name', 'id')),
                SelectFilter::make('subject_id')
                    ->label('Matière')
                    ->options(fn () => Subject::query()->where('user_id', Auth::id())->pluck('name', 'id')),
                SelectFilter::make('term_id')
                    ->label('Trimestre')
                    ->options(fn () => Term::query()->where('user_id', Auth::id())->orderBy('position')->pluck('label', 'id')),
            ])
            ->recordActions([
                Action::make('grades')
                    ->label('Saisir les notes')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('primary')
                    ->url(fn ($record) => EvaluationGrades::getUrl(['evaluation' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
