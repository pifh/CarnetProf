<?php

namespace App\Policies;

use App\Models\LogbookEntry;
use App\Models\User;

class LogbookEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LogbookEntry $logbookEntry): bool
    {
        return $logbookEntry->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LogbookEntry $logbookEntry): bool
    {
        return $logbookEntry->user_id === $user->id;
    }

    public function delete(User $user, LogbookEntry $logbookEntry): bool
    {
        return $logbookEntry->user_id === $user->id;
    }

    public function restore(User $user, LogbookEntry $logbookEntry): bool
    {
        return $logbookEntry->user_id === $user->id;
    }

    public function forceDelete(User $user, LogbookEntry $logbookEntry): bool
    {
        return $logbookEntry->user_id === $user->id;
    }
}
