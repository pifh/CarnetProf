<?php

namespace App\Policies;

use App\Models\StudentSubgroup;
use App\Models\User;

class StudentSubgroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StudentSubgroup $studentSubgroup): bool
    {
        return $studentSubgroup->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, StudentSubgroup $studentSubgroup): bool
    {
        return $studentSubgroup->user_id === $user->id;
    }

    public function delete(User $user, StudentSubgroup $studentSubgroup): bool
    {
        return $studentSubgroup->user_id === $user->id;
    }

    public function restore(User $user, StudentSubgroup $studentSubgroup): bool
    {
        return $studentSubgroup->user_id === $user->id;
    }

    public function forceDelete(User $user, StudentSubgroup $studentSubgroup): bool
    {
        return $studentSubgroup->user_id === $user->id;
    }
}
