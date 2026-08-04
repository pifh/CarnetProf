<?php

namespace App\Filament\Resources\CalendarEvents\Schemas;

use App\Models\CalendarEvent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CalendarEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Type')
                    ->options(CalendarEvent::typeLabels())
                    ->required(),

                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),

                Toggle::make('all_day')
                    ->label('Journée entière')
                    ->helperText("À décocher une fois l'heure précisée")
                    ->live()
                    ->default(true),

                DatePicker::make('starts_at')
                    ->label('Début')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now())
                    ->visible(fn (Get $get) => $get('all_day'))
                    ->dehydrated(fn (Get $get) => $get('all_day'))
                    ->required(fn (Get $get) => $get('all_day')),

                DateTimePicker::make('starts_at')
                    ->label('Début')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
                    ->default(now())
                    ->visible(fn (Get $get) => ! $get('all_day'))
                    ->dehydrated(fn (Get $get) => ! $get('all_day'))
                    ->required(fn (Get $get) => ! $get('all_day')),

                DatePicker::make('ends_at')
                    ->label('Fin (optionnel)')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Get $get) => $get('all_day'))
                    ->dehydrated(fn (Get $get) => $get('all_day')),

                DateTimePicker::make('ends_at')
                    ->label('Fin (optionnel)')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn (Get $get) => ! $get('all_day'))
                    ->dehydrated(fn (Get $get) => ! $get('all_day')),

                MarkdownEditor::make('notes')
                    ->label('Compte rendu')
                    ->columnSpanFull(),

                Repeater::make('attachments')
                    ->relationship()
                    ->label('Fichiers joints')
                    ->schema([
                        FileUpload::make('path')
                            ->label('Fichier')
                            ->disk('public')
                            ->directory('calendar-events')
                            ->acceptedFileTypes(['image/*', 'video/*', 'audio/*'])
                            ->storeFileNamesIn('original_filename')
                            ->maxSize(51200)
                            ->downloadable()
                            ->openable()
                            ->required(),
                    ])
                    ->addActionLabel('Ajouter un fichier')
                    ->reorderable(false)
                    ->collapsible()
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
