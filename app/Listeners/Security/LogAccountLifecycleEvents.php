<?php

namespace App\Listeners\Security;

use App\Models\User;
use App\Services\Observability\SecurityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;

/**
 * The lower-frequency account events, grouped because each is a two-line handler.
 * Laravel's event discovery registers each `handle*` method against its parameter type.
 */
class LogAccountLifecycleEvents
{
    public function __construct(private SecurityLogger $security) {}

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->log($event->user, 'password_reset', 'إعادة تعيين كلمة المرور');
    }

    public function handleVerified(Verified $event): void
    {
        $this->log($event->user, 'email_verified', 'تأكيد البريد الإلكتروني');
    }

    public function handleRegistered(Registered $event): void
    {
        $this->log($event->user, 'registered', 'تسجيل حساب جديد');
    }

    protected function log(mixed $user, string $event, string $description): void
    {
        if (! $user instanceof User) {
            return;
        }

        $this->security->record(event: $event, description: $description, user: $user);
    }
}
