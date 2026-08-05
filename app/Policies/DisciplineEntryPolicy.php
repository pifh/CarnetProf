<?php

namespace App\Policies;

use App\Models\DisciplineEntry;
use App\Models\User;

class DisciplineEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DisciplineEntry $disciplineEntry): bool
    {
        return $disciplineEntry->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DisciplineEntry $disciplineEntry): bool
    {
        return $disciplineEntry->user_id === $user->id;
    }

    public function delete(User $user, DisciplineEntry $disciplineEntry): bool
    {
        return $disciplineEntry->user_id === $user->id;
    }

    public function restore(User $user, DisciplineEntry $disciplineEntry): bool
    {
        return $disciplineEntry->user_id === $user->id;
    }

    public function forceDelete(User $user, DisciplineEntry $disciplineEntry): bool
    {
        return $disciplineEntry->user_id === $user->id;
    }
}
