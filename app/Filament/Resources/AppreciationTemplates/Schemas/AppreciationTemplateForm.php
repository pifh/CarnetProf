<?php

namespace App\Filament\Resources\AppreciationTemplates\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AppreciationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('ex. Bon trimestre, Élève sérieux...'),

                Textarea::make('content')
                    ->label('Texte de l\'appréciation')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }
}
