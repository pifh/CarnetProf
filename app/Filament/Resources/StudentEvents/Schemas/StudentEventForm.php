<?php

namespace App\Filament\Resources\StudentEvents\Schemas;

use App\Models\Student;
use App\Models\StudentEvent;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->required(),

                TextInput::make('type')
                    ->label("Type d'événement")
                    ->required()
                    ->maxLength(255)
                    ->datalist(fn () => StudentEvent::allTypes())
                    ->placeholder('ex. Réunion parents, Avertissement, Rencontre mensuelle...'),

                DatePicker::make('event_date')
                    ->label('Date')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now())
                    ->required(),

                Textarea::make('notes')
                    ->label('Compte rendu')
                    ->rows(4)
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
