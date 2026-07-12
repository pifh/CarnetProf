<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return $subject->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Subject $subject): bool
    {
        return $subject->user_id === $user->id;
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $subject->user_id === $user->id;
    }

    public function restore(User $user, Subject $subject): bool
    {
        return $subject->user_id === $user->id;
    }

    public function forceDelete(User $user, Subject $subject): bool
    {
        return $subject->user_id === $user->id;
    }
}
