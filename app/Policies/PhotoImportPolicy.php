<?php

namespace App\Policies;

use App\Models\PhotoImport;
use App\Models\User;

class PhotoImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PhotoImport $photoImport): bool
    {
        return $photoImport->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PhotoImport $photoImport): bool
    {
        return $photoImport->user_id === $user->id;
    }

    public function delete(User $user, PhotoImport $photoImport): bool
    {
        return $photoImport->user_id === $user->id;
    }

    public function restore(User $user, PhotoImport $photoImport): bool
    {
        return $photoImport->user_id === $user->id;
    }

    public function forceDelete(User $user, PhotoImport $photoImport): bool
    {
        return $photoImport->user_id === $user->id;
    }
}
