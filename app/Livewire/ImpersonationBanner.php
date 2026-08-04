<?php

namespace App\Livewire;

use App\Models\ImpersonationLog;
use App\Models\User;
use App\Services\ImpersonationManager;
use Livewire\Component;

/**
 * Mounted once globally (see AppPanelProvider's body-start render hook).
 * Reads the impersonation state from the session rather than from the
 * currently authenticated user's role, since while impersonating,
 * Auth::user() genuinely *is* the target teacher for the rest of the app
 * (see BelongsToTeacher) — only the session key tells us an admin is behind
 * the wheel.
 */
class ImpersonationBanner extends Component
{
    public function getImpersonatorProperty(): ?User
    {
        $id = session('impersonator_id');

        return $id ? User::find($id) : null;
    }

    public function stop(): void
    {
        $adminId = session('impersonator_id');

        if (! $adminId) {
            return;
        }

        ImpersonationLog::query()->find(session('impersonation_log_id'))?->update(['ended_at' => now()]);

        session()->forget(['impersonator_id', 'impersonation_log_id']);
        app(ImpersonationManager::class)->stop($adminId);

        $this->redirect('/', navigate: false);
    }

    public function render()
    {
        return view('livewire.impersonation-banner');
    }
}
