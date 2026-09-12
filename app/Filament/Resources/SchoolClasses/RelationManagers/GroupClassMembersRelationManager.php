<?php

namespace App\Filament\Resources\SchoolClasses\RelationManagers;

use App\Models\Student;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GroupClassMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'groupClassMembers';

    protected static ?string $title = 'Élèves du groupe classe';

    protected static ?string $modelLabel = 'élève';

    protected static ?string $pluralModelLabel = 'élèves';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->inverseRelationship('groupClasses')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable(),
                TextColumn::make('schoolClass.name')
                    ->label('Classe d\'origine'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Ajouter un élève')
                    ->recordTitle(fn (Student $student) => $student->full_name.($student->schoolClass ? ' — '.$student->schoolClass->name : ''))
                    ->recordSelectSearchColumns(['first_name', 'last_name'])
                    ->recordSelectOptionsQuery(fn ($query) => $query
                        ->where('students.user_id', Auth::id())
                        ->where('students.is_archived', false)
                        ->where('students.school_class_id', '!=', $this->getOwnerRecord()->id)
                    ),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
