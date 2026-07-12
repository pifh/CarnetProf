<?php

namespace App\Policies;

use App\Models\Term;
use App\Models\User;

class TermPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Term $term): bool
    {
        return $term->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Term $term): bool
    {
        return $term->user_id === $user->id;
    }

    public function delete(User $user, Term $term): bool
    {
        return $term->user_id === $user->id;
    }

    public function restore(User $user, Term $term): bool
    {
        return $term->user_id === $user->id;
    }

    public function forceDelete(User $user, Term $term): bool
    {
        return $term->user_id === $user->id;
    }
}
