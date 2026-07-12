<?php

namespace App\Filament\Widgets;

use App\Models\Reminder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PersonalReminders extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Rappels personnels';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Reminder::query())
            ->defaultSort('is_done')
            ->paginated(false)
            ->emptyStateHeading('Aucun rappel')
            ->emptyStateDescription('Ajoutez un rappel pour commencer.')
            ->columns([
                ToggleColumn::make('is_done')
                    ->label('Fait'),
                TextColumn::make('title')
                    ->label('Rappel'),
                TextColumn::make('due_date')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Ajouter un rappel')
                    ->modalHeading('Ajouter un rappel')
                    ->schema([
                        TextInput::make('title')
                            ->label('Rappel')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('due_date')
                            ->label('Échéance')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading('Supprimer ce rappel'),
            ]);
    }
}
