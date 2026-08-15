<?php

namespace App\Listeners\Security;

use App\Services\Observability\SecurityLogger;
use Illuminate\Auth\Events\Lockout;

/**
 * The Lockout event is already dispatched by LoginRequest::ensureIsNotRateLimited()
 * and previously had no listener at all — a brute-force lockout left no trace.
 */
class LogLockout
{
    public function __construct(private SecurityLogger $security) {}

    public function handle(Lockout $event): void
    {
        $email = $event->request->input('email');

        $this->security->record(
            event: 'lockout',
            description: 'حظر مؤقت بعد محاولات دخول متكررة',
            properties: ['attempted_email' => $email],
            throttleBy: mb_strtolower((string) $email).'|'.$event->request->ip(),
        );
    }
}
