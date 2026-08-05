<?php

namespace App\Policies;

use App\Models\DisciplineReset;
use App\Models\User;

class DisciplineResetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DisciplineReset $disciplineReset): bool
    {
        return $disciplineReset->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DisciplineReset $disciplineReset): bool
    {
        return $disciplineReset->user_id === $user->id;
    }

    public function delete(User $user, DisciplineReset $disciplineReset): bool
    {
        return $disciplineReset->user_id === $user->id;
    }

    public function restore(User $user, DisciplineReset $disciplineReset): bool
    {
        return $disciplineReset->user_id === $user->id;
    }

    public function forceDelete(User $user, DisciplineReset $disciplineReset): bool
    {
        return $disciplineReset->user_id === $user->id;
    }
}
