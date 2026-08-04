<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->disabled()
                    ->dehydrated(false),

                TextInput::make('email')
                    ->label('Email')
                    ->disabled()
                    ->dehydrated(false),

                Select::make('role')
                    ->label('Rôle')
                    ->options([
                        User::ROLE_TEACHER => 'Enseignant',
                        User::ROLE_ADMIN => 'Administrateur',
                        User::ROLE_SUPERADMIN => 'Superadministrateur',
                    ])
                    ->disabled(fn () => ! Auth::user()->isSuperadmin())
                    ->required(),
            ]);
    }
}
