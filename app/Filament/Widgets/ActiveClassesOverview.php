<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SchoolClasses\SchoolClassResource;
use App\Models\SchoolClass;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveClassesOverview extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Classes actives';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SchoolClass::query()
                ->where('is_archived', false)
                ->withCount(['students' => fn ($query) => $query->where('is_archived', false)]))
            ->paginated(false)
            ->emptyStateHeading('Aucune classe active')
            ->recordUrl(fn (SchoolClass $record) => SchoolClassResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Classe')
                    ->badge()
                    ->color(fn (SchoolClass $record) => $record->color ?? 'gray'),
                TextColumn::make('level')
                    ->label('Niveau'),
                TextColumn::make('students_count')
                    ->label('Élèves'),
            ]);
    }
}
