<?php

namespace App\Filament\Resources\Students\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardians';

    protected static ?string $title = 'Responsables légaux';

    protected static ?string $modelLabel = 'responsable légal';

    protected static ?string $pluralModelLabel = 'responsables légaux';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('Prénom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('relationship')
                    ->label('Lien avec l\'élève')
                    ->maxLength(255)
                    ->placeholder('Mère, père, tuteur légal...'),
                TextInput::make('phone')
                    ->label('Téléphone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                Checkbox::make('is_primary')
                    ->label('Contact principal'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('relationship')
                    ->label('Lien'),
                TextColumn::make('phone')
                    ->label('Téléphone'),
                TextColumn::make('email')
                    ->label('Email'),
                IconColumn::make('is_primary')
                    ->label('Principal')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
                AttachAction::make()
                    ->recordSelectOptionsQuery(fn ($query) => $query)
                    ->schema(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        Checkbox::make('is_primary')->label('Contact principal'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
