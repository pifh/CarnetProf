<?php

namespace App\Policies;

use App\Models\SeatingPlan;
use App\Models\User;

class SeatingPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SeatingPlan $seatingPlan): bool
    {
        return $seatingPlan->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SeatingPlan $seatingPlan): bool
    {
        return $seatingPlan->user_id === $user->id;
    }

    public function delete(User $user, SeatingPlan $seatingPlan): bool
    {
        return $seatingPlan->user_id === $user->id;
    }

    public function restore(User $user, SeatingPlan $seatingPlan): bool
    {
        return $seatingPlan->user_id === $user->id;
    }

    public function forceDelete(User $user, SeatingPlan $seatingPlan): bool
    {
        return $seatingPlan->user_id === $user->id;
    }
}
