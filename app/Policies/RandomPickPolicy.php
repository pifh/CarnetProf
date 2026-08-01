<?php

namespace App\Policies;

use App\Models\RandomPick;
use App\Models\User;

class RandomPickPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RandomPick $randomPick): bool
    {
        return $randomPick->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RandomPick $randomPick): bool
    {
        return $randomPick->user_id === $user->id;
    }

    public function delete(User $user, RandomPick $randomPick): bool
    {
        return $randomPick->user_id === $user->id;
    }

    public function restore(User $user, RandomPick $randomPick): bool
    {
        return $randomPick->user_id === $user->id;
    }

    public function forceDelete(User $user, RandomPick $randomPick): bool
    {
        return $randomPick->user_id === $user->id;
    }
}
