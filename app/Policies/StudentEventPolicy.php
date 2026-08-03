<?php

namespace App\Policies;

use App\Models\StudentEvent;
use App\Models\User;

class StudentEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StudentEvent $studentEvent): bool
    {
        return $studentEvent->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, StudentEvent $studentEvent): bool
    {
        return $studentEvent->user_id === $user->id;
    }

    public function delete(User $user, StudentEvent $studentEvent): bool
    {
        return $studentEvent->user_id === $user->id;
    }

    public function restore(User $user, StudentEvent $studentEvent): bool
    {
        return $studentEvent->user_id === $user->id;
    }

    public function forceDelete(User $user, StudentEvent $studentEvent): bool
    {
        return $studentEvent->user_id === $user->id;
    }
}
