<?php

namespace App\Policies;

use App\Models\PersonalBirthday;
use App\Models\User;

class PersonalBirthdayPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PersonalBirthday $personalBirthday): bool
    {
        return $personalBirthday->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PersonalBirthday $personalBirthday): bool
    {
        return $personalBirthday->user_id === $user->id;
    }

    public function delete(User $user, PersonalBirthday $personalBirthday): bool
    {
        return $personalBirthday->user_id === $user->id;
    }

    public function restore(User $user, PersonalBirthday $personalBirthday): bool
    {
        return $personalBirthday->user_id === $user->id;
    }

    public function forceDelete(User $user, PersonalBirthday $personalBirthday): bool
    {
        return $personalBirthday->user_id === $user->id;
    }
}
