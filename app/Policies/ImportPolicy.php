<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function delete(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function restore(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }

    public function forceDelete(User $user, Import $import): bool
    {
        return $import->user_id === $user->id;
    }
}
