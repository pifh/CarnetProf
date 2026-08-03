<?php

namespace App\Policies;

use App\Models\RandomPickSession;
use App\Models\User;

class RandomPickSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RandomPickSession $randomPickSession): bool
    {
        return $randomPickSession->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RandomPickSession $randomPickSession): bool
    {
        return $randomPickSession->user_id === $user->id;
    }

    public function delete(User $user, RandomPickSession $randomPickSession): bool
    {
        return $randomPickSession->user_id === $user->id;
    }

    public function restore(User $user, RandomPickSession $randomPickSession): bool
    {
        return $randomPickSession->user_id === $user->id;
    }

    public function forceDelete(User $user, RandomPickSession $randomPickSession): bool
    {
        return $randomPickSession->user_id === $user->id;
    }
}
