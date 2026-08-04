<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:promote {email} {role=admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Change le rôle d'un utilisateur (teacher, admin, superadmin) — pour une intervention manuelle ponctuelle.";

    public function handle(): int
    {
        $email = $this->argument('email');
        $role = $this->argument('role');

        if (! in_array($role, [User::ROLE_TEACHER, User::ROLE_ADMIN, User::ROLE_SUPERADMIN], true)) {
            $this->error("Rôle invalide : {$role}. Valeurs possibles : teacher, admin, superadmin.");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun utilisateur avec l'email {$email}");

            return self::FAILURE;
        }

        $user->update(['role' => $role]);

        $this->info("{$user->name} ({$email}) est maintenant : {$role}");

        return self::SUCCESS;
    }
}
