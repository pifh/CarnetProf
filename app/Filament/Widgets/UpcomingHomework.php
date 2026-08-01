<?php

namespace App\Filament\Widgets;

use App\Models\LogbookEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UpcomingHomework extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Devoirs à venir';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => LogbookEntry::query()
                ->where('user_id', Auth::id())
                ->whereNotNull('homework_due_date')
                ->whereDate('homework_due_date', '>=', now())
                ->orderBy('homework_due_date'))
            ->paginated(false)
            ->emptyStateHeading('Aucun devoir à venir')
            ->columns([
                TextColumn::make('homework_due_date')
                    ->label('À rendre le')
                    ->date('d/m/Y'),
                TextColumn::make('schoolClass.name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn (LogbookEntry $record) => $record->schoolClass?->color ?? 'gray'),
                TextColumn::make('homework')
                    ->label('Travail à faire')
                    ->limit(60)
                    ->wrap(),
            ]);
    }
}
