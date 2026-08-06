<?php

namespace App\Filament\Resources\StudentEvents\Schemas;

use App\Models\Student;
use App\Models\StudentEvent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StudentEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Élève')
                    ->relationship(
                        name: 'student',
                        titleAttribute: 'first_name',
                        modifyQueryUsing: fn ($query) => $query->where('is_archived', false),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Student $student) => $student->full_name.($student->schoolClass ? ' — '.$student->schoolClass->name : ''))
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->live()
                    ->required(),

                TextInput::make('type')
                    ->label("Type d'événement")
                    ->required()
                    ->maxLength(255)
                    ->datalist(fn () => StudentEvent::allTypes())
                    ->placeholder('ex. Réunion parents, Avertissement, Rencontre mensuelle...'),

                Toggle::make('all_day')
                    ->label('Journée entière')
                    ->helperText("À décocher une fois l'heure précisée")
                    ->live()
                    ->default(true),

                DatePicker::make('starts_at')
                    ->label('Date')
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

                DateTimePicker::make('ends_at')
                    ->label('Fin (optionnel)')
                    ->native(false)
                    ->seconds(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn (Get $get) => ! $get('all_day'))
                    ->dehydrated(fn (Get $get) => ! $get('all_day')),

                ViewField::make('student_fiche')
                    ->label('')
                    ->view('filament.pages.partials.student-fiche-embed')
                    ->viewData(fn (Get $get) => ['studentId' => $get('student_id')])
                    ->visible(fn (Get $get) => filled($get('student_id')))
                    ->dehydrated(false)
                    ->columnSpanFull(),

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
                            ->directory('student-events')
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
