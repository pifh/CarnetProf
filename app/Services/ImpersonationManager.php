<?php

namespace App\Services;

use App\Models\ImpersonationLog;
use App\Models\User;
use BadMethodCallException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;

class ImpersonationManager
{
    public function start(User $admin, User $target): void
    {
        Gate::forUser($admin)->authorize('impersonate', $target);

        $log = ImpersonationLog::create([
            'admin_id' => $admin->id,
            'target_user_id' => $target->id,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'started_at' => now(),
        ]);

        session(['impersonator_id' => $admin->id, 'impersonation_log_id' => $log->id]);

        Auth::loginUsingId($target->id);

        $this->refreshSessionPasswordHash($target);
    }

    public function stop(int $adminId): void
    {
        Auth::loginUsingId($adminId);

        $this->refreshSessionPasswordHash(Auth::user());
    }

    /**
     * Livewire's `/livewire/update` endpoint only runs the plain `web`
     * middleware group, not the Filament panel's `AuthenticateSession`
     * middleware — so swapping Auth::user() mid-Livewire-request never
     * triggers that middleware's own session password-hash refresh. The
     * next full-page navigation (which *does* run AuthenticateSession)
     * would then see the OLD user's stale hash, decide the session was
     * tampered with, and force a full logout. Refreshing it ourselves,
     * right after every login swap, keeps that check consistent.
     */
    private function refreshSessionPasswordHash(User $user): void
    {
        $guard = Auth::guard();
        $passwordHash = $user->getAuthPassword();

        try {
            $passwordHash = $guard->hashPasswordForCookie($passwordHash);
        } catch (BadMethodCallException) {
        }

        session(['password_hash_'.Auth::getDefaultDriver() => $passwordHash]);
    }
}
