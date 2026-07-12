<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Grade $grade): bool
    {
        return $grade->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Grade $grade): bool
    {
        return $grade->user_id === $user->id;
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $grade->user_id === $user->id;
    }

    public function restore(User $user, Grade $grade): bool
    {
        return $grade->user_id === $user->id;
    }

    public function forceDelete(User $user, Grade $grade): bool
    {
        return $grade->user_id === $user->id;
    }
}
