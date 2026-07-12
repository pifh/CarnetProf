<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TodaysBirthdays extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Anniversaires du jour';

    public function table(Table $table): Table
    {
        $today = Carbon::today();

        return $table
            ->query(fn (): Builder => Student::query()
                ->where('is_archived', false)
                ->whereNotNull('birth_date')
                ->whereMonth('birth_date', $today->month)
                ->whereDay('birth_date', $today->day))
            ->paginated(false)
            ->emptyStateHeading('Aucun anniversaire aujourd\'hui')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Élève')
                    ->state(fn (Student $record) => "{$record->first_name} {$record->last_name}"),
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn (Student $record) => $record->schoolClass?->color ?? 'gray'),
                TextColumn::make('birth_date')
                    ->label('A')
                    ->formatStateUsing(fn (Student $record) => $record->birth_date->diffInYears(now()).' ans'),
            ]);
    }
}
