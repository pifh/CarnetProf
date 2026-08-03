<?php

namespace App\Policies;

use App\Models\SeatingPlanApplication;
use App\Models\User;

class SeatingPlanApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SeatingPlanApplication $seatingPlanApplication): bool
    {
        return $seatingPlanApplication->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SeatingPlanApplication $seatingPlanApplication): bool
    {
        return $seatingPlanApplication->user_id === $user->id;
    }

    public function delete(User $user, SeatingPlanApplication $seatingPlanApplication): bool
    {
        return $seatingPlanApplication->user_id === $user->id;
    }

    public function restore(User $user, SeatingPlanApplication $seatingPlanApplication): bool
    {
        return $seatingPlanApplication->user_id === $user->id;
    }

    public function forceDelete(User $user, SeatingPlanApplication $seatingPlanApplication): bool
    {
        return $seatingPlanApplication->user_id === $user->id;
    }
}
