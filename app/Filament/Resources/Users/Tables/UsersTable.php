<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\ImpersonationManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        User::ROLE_SUPERADMIN => 'Superadministrateur',
                        User::ROLE_ADMIN => 'Administrateur',
                        default => 'Enseignant',
                    })
                    ->color(fn (string $state) => match ($state) {
                        User::ROLE_SUPERADMIN => 'danger',
                        User::ROLE_ADMIN => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('Rôle')
                    ->options([
                        User::ROLE_TEACHER => 'Enseignant',
                        User::ROLE_ADMIN => 'Administrateur',
                        User::ROLE_SUPERADMIN => 'Superadministrateur',
                    ]),
            ])
            ->recordActions([
                Action::make('impersonate')
                    ->label('Se connecter en tant que')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->color('gray')
                    ->visible(fn (User $record) => ! session()->has('impersonator_id') && Gate::allows('impersonate', $record))
                    ->requiresConfirmation()
                    ->action(function (User $record, $livewire) {
                        app(ImpersonationManager::class)->start(Auth::user(), $record);

                        $livewire->redirect('/', navigate: false);
                    }),
                EditAction::make(),
            ]);
    }
}
