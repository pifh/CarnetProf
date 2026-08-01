<?php

namespace App\Policies;

use App\Models\ProgressionSequence;
use App\Models\User;

class ProgressionSequencePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProgressionSequence $progressionSequence): bool
    {
        return $progressionSequence->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ProgressionSequence $progressionSequence): bool
    {
        return $progressionSequence->user_id === $user->id;
    }

    public function delete(User $user, ProgressionSequence $progressionSequence): bool
    {
        return $progressionSequence->user_id === $user->id;
    }

    public function restore(User $user, ProgressionSequence $progressionSequence): bool
    {
        return $progressionSequence->user_id === $user->id;
    }

    public function forceDelete(User $user, ProgressionSequence $progressionSequence): bool
    {
        return $progressionSequence->user_id === $user->id;
    }
}
