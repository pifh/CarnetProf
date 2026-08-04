<?php

namespace App\Policies;

use App\Models\BackupDestination;
use App\Models\User;

class BackupDestinationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BackupDestination $backupDestination): bool
    {
        return $backupDestination->isPersonal() ? $backupDestination->user_id === $user->id : $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, BackupDestination $backupDestination): bool
    {
        return $this->view($user, $backupDestination);
    }

    public function delete(User $user, BackupDestination $backupDestination): bool
    {
        return $this->view($user, $backupDestination);
    }

    public function restore(User $user, BackupDestination $backupDestination): bool
    {
        return $this->view($user, $backupDestination);
    }

    public function forceDelete(User $user, BackupDestination $backupDestination): bool
    {
        return $this->view($user, $backupDestination);
    }
}
