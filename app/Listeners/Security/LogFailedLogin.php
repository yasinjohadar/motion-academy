<?php

namespace App\Listeners\Security;

use App\Services\Observability\SecurityLogger;
use Illuminate\Auth\Events\Failed;

/**
 * Failed logins are unauthenticated, so there is no causer — the attempted email is
 * recorded in the properties instead.
 *
 * Throttled per (email + IP): a credential-stuffing run would otherwise write
 * thousands of rows and bury every other security event.
 */
class LogFailedLogin
{
    public function __construct(private SecurityLogger $security) {}

    public function handle(Failed $event): void
    {
        $email = $this->attemptedEmail($event);

        $this->security->record(
            event: 'login_failed',
            description: 'محاولة دخول فاشلة',
            properties: [
                'attempted_email' => $email,
                'user_exists' => $event->user !== null,
                'guard' => $event->guard,
            ],
            throttleBy: mb_strtolower((string) $email).'|'.request()->ip(),
        );
    }

    /**
     * Reads only the identifier — never the attempted password, which arrives in
     * $event->credentials marked #[SensitiveParameter].
     */
    protected function attemptedEmail(Failed $event): ?string
    {
        $credentials = is_array($event->credentials) ? $event->credentials : [];

        $email = $credentials['email'] ?? $credentials['username'] ?? null;

        return is_string($email) ? $email : null;
    }
}
