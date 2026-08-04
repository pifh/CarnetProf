<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isSuperadmin() && $user->id !== $model->id;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isSuperadmin() && $user->id !== $model->id;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperadmin() && $user->id !== $model->id;
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->isAdmin() && $model->role === User::ROLE_TEACHER && $user->id !== $model->id;
    }
}
