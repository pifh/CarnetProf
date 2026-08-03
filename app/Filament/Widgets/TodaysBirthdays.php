<?php

namespace App\Filament\Widgets;

use App\Models\Student;
use Filament\Tables\Columns\ImageColumn;
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
                ImageColumn::make('photo_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn (Student $record) => $this->initialsAvatarUrl($record))
                    ->action(fn (Student $record, $livewire) => $livewire->dispatch('open-student-preview', studentId: $record->id)),
                TextColumn::make('full_name')
                    ->label('Élève')
                    ->state(fn (Student $record) => "{$record->first_name} {$record->last_name}")
                    ->action(fn (Student $record, $livewire) => $livewire->dispatch('open-student-preview', studentId: $record->id)),
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn (Student $record) => $record->schoolClass?->color ?? 'gray'),
                TextColumn::make('birth_date')
                    ->label('A')
                    // diffInYears returns a precise float (e.g. 11.000768345534), not
                    // a whole number of years, so it must be rounded before display.
                    ->formatStateUsing(fn (Student $record) => round($record->birth_date->diffInYears(now())).' ans'),
            ]);
    }

    private function initialsAvatarUrl(Student $student): string
    {
        $initials = mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1));

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">
            <rect width="40" height="40" rx="20" fill="#e5e7eb" />
            <text x="20" y="21" fill="#6b7280" font-family="sans-serif" font-size="16" font-weight="600" text-anchor="middle" dominant-baseline="central">{$initials}</text>
        </svg>
        SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
