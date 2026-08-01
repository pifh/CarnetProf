<?php

namespace App\Policies;

use App\Models\AppreciationTemplate;
use App\Models\User;

class AppreciationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AppreciationTemplate $appreciationTemplate): bool
    {
        return $appreciationTemplate->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AppreciationTemplate $appreciationTemplate): bool
    {
        return $appreciationTemplate->user_id === $user->id;
    }

    public function delete(User $user, AppreciationTemplate $appreciationTemplate): bool
    {
        return $appreciationTemplate->user_id === $user->id;
    }

    public function restore(User $user, AppreciationTemplate $appreciationTemplate): bool
    {
        return $appreciationTemplate->user_id === $user->id;
    }

    public function forceDelete(User $user, AppreciationTemplate $appreciationTemplate): bool
    {
        return $appreciationTemplate->user_id === $user->id;
    }
}
