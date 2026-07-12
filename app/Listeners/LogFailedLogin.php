<?php

namespace App\Listeners;

use App\Models\LoginLog;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        LoginLog::create([
            'user_id' => $event->user?->getAuthIdentifier(),
            'email' => $event->credentials['email'] ?? 'unknown',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'successful' => false,
        ]);
    }
}
