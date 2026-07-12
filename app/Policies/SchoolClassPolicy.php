<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SchoolClass $schoolClass): bool
    {
        return $schoolClass->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SchoolClass $schoolClass): bool
    {
        return $schoolClass->user_id === $user->id;
    }

    public function delete(User $user, SchoolClass $schoolClass): bool
    {
        return $schoolClass->user_id === $user->id;
    }

    public function restore(User $user, SchoolClass $schoolClass): bool
    {
        return $schoolClass->user_id === $user->id;
    }

    public function forceDelete(User $user, SchoolClass $schoolClass): bool
    {
        return $schoolClass->user_id === $user->id;
    }
}
