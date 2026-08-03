<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSubgroup;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Élève')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Identité')
                            ->schema([
                                Select::make('school_class_id')
                                    ->label('Classe')
                                    ->relationship(
                                        name: 'schoolClass',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->where('user_id', Auth::id())->where('is_archived', false),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),

                                TextInput::make('first_name')
                                    ->label('Prénom')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('last_name')
                                    ->label('Nom')
                                    ->required()
                                    ->maxLength(255),

                                Radio::make('sex')
                                    ->label('Sexe')
                                    ->options([
                                        'f' => 'Fille',
                                        'm' => 'Garçon',
                                        'other' => 'Autre',
                                    ])
                                    ->inline(),

                                DatePicker::make('birth_date')
                                    ->label('Date de naissance')
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),

                                Toggle::make('is_delegate')
                                    ->label('Délégué(e) de classe'),
                            ])
                            ->columns(2),

                        Tab::make('Contact')
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->maxLength(30),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),

                                Textarea::make('address')
                                    ->label('Adresse')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Groupes')
                            ->schema([
                                Select::make('subgroups')
                                    ->label('Groupes')
                                    ->relationship(name: 'subgroups', titleAttribute: 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Uniquement les groupes de la classe sélectionnée.')
                                    ->options(function (callable $get) {
                                        $schoolClassId = $get('school_class_id');

                                        if (! $schoolClassId) {
                                            return [];
                                        }

                                        return SchoolClass::query()
                                            ->find($schoolClassId)
                                            ?->subgroups()
                                            ->pluck('name', 'id') ?? [];
                                    })
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nom du groupe')
                                            ->required()
                                            ->maxLength(255),
                                        ColorPicker::make('color')
                                            ->label('Couleur')
                                            ->default('#6b7280'),
                                    ])
                                    ->createOptionAction(fn ($action) => $action->modalHeading('Nouveau groupe'))
                                    ->createOptionUsing(function (array $data, callable $get) {
                                        $data['school_class_id'] = $get('school_class_id');

                                        return StudentSubgroup::create($data)->getKey();
                                    }),
                            ]),

                        Tab::make('Plan de classe')
                            ->schema([
                                TagsInput::make('seating_allowed_rows')
                                    ->label('Rangs possibles')
                                    ->placeholder('Ajouter un numéro de rang')
                                    ->helperText('Numéros de rangs autorisés (1, 2, 3...). Laisser vide si aucune contrainte.'),

                                TagsInput::make('seating_allowed_columns')
                                    ->label('Colonnes possibles')
                                    ->placeholder('Ajouter un numéro de colonne')
                                    ->helperText('Numéros de colonnes autorisées (1, 2, 3...). Laisser vide si aucune contrainte.'),

                                Select::make('seatingNextTo')
                                    ->label('À côté de')
                                    ->relationship(name: 'seatingNextTo', titleAttribute: 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (Student $student) => $student->full_name)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Le générateur de plan de classe placera cet élève au même bureau.')
                                    ->options(fn (callable $get, ?Student $record) => self::classmateOptions($get('school_class_id'), $record))
                                    ->columnSpanFull(),

                                Select::make('seatingNotNextTo')
                                    ->label('Pas à côté de')
                                    ->relationship(name: 'seatingNotNextTo', titleAttribute: 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (Student $student) => $student->full_name)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Le générateur évitera de placer cet élève au même bureau.')
                                    ->options(fn (callable $get, ?Student $record) => self::classmateOptions($get('school_class_id'), $record))
                                    ->columnSpanFull(),

                                Select::make('seatingFarFrom')
                                    ->label('À séparer le plus possible de')
                                    ->relationship(name: 'seatingFarFrom', titleAttribute: 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (Student $student) => $student->full_name)
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Le générateur placera cet élève aussi loin que possible.')
                                    ->options(fn (callable $get, ?Student $record) => self::classmateOptions($get('school_class_id'), $record))
                                    ->columnSpanFull(),

                                Textarea::make('seating_notes')
                                    ->label('Notes libres')
                                    ->helperText('Non utilisées par le générateur, à titre indicatif.')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),

                        Tab::make('Pédagogie (privé)')
                            ->schema([
                                TagsInput::make('special_needs')
                                    ->label('Besoins particuliers')
                                    ->placeholder('Ajouter un mot clef')
                                    ->suggestions(fn () => Student::allSpecialNeedsTags())
                                    ->splitKeys(['Tab', ','])
                                    ->columnSpanFull(),

                                Textarea::make('pedagogical_notes')
                                    ->label('Consignes pédagogiques')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Textarea::make('private_notes')
                                    ->label('Commentaires privés')
                                    ->helperText('Visibles uniquement par vous.')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function classmateOptions(mixed $schoolClassId, ?Student $record): array
    {
        if (! $schoolClassId) {
            return [];
        }

        return Student::query()
            ->where('school_class_id', $schoolClassId)
            ->where('is_archived', false)
            ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Student $student) => [$student->id => $student->full_name])
            ->all();
    }
}
