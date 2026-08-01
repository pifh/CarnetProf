<?php

namespace App\Policies;

use App\Models\Appreciation;
use App\Models\User;

class AppreciationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appreciation $appreciation): bool
    {
        return $appreciation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Appreciation $appreciation): bool
    {
        return $appreciation->user_id === $user->id;
    }

    public function delete(User $user, Appreciation $appreciation): bool
    {
        return $appreciation->user_id === $user->id;
    }

    public function restore(User $user, Appreciation $appreciation): bool
    {
        return $appreciation->user_id === $user->id;
    }

    public function forceDelete(User $user, Appreciation $appreciation): bool
    {
        return $appreciation->user_id === $user->id;
    }
}
