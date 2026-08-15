<?php

namespace App\Listeners\Security;

use App\Models\User;
use App\Services\Observability\SecurityLogger;
use Illuminate\Auth\Events\Login;

/**
 * Discovered automatically by Laravel's event discovery — do not register manually.
 */
class LogSuccessfulLogin
{
    public function __construct(private SecurityLogger $security) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->security->record(
            event: 'login',
            description: 'تسجيل دخول ناجح',
            user: $user,
            properties: [
                'guard' => $event->guard,
                'remember' => (bool) $event->remember,
            ],
        );

        // saveQuietly: this is bookkeeping, not an audited change — logging it would
        // add a noisy `users` activity row on every single login.
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();
    }
}
