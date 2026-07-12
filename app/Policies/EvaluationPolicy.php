<?php

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->user_id === $user->id;
    }

    public function delete(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->user_id === $user->id;
    }

    public function restore(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->user_id === $user->id;
    }

    public function forceDelete(User $user, Evaluation $evaluation): bool
    {
        return $evaluation->user_id === $user->id;
    }
}
