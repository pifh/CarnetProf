<?php

namespace App\Policies;

use App\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->user_id === $user->id;
    }

    public function delete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->user_id === $user->id;
    }

    public function restore(User $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->user_id === $user->id;
    }

    public function forceDelete(User $user, CalendarEvent $calendarEvent): bool
    {
        return $calendarEvent->user_id === $user->id;
    }
}
