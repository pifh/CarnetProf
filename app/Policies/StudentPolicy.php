<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Student $student): bool
    {
        return $student->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Student $student): bool
    {
        return $student->user_id === $user->id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $student->user_id === $user->id;
    }

    public function restore(User $user, Student $student): bool
    {
        return $student->user_id === $user->id;
    }

    public function forceDelete(User $user, Student $student): bool
    {
        return $student->user_id === $user->id;
    }
}
