<?php

namespace App\Listeners\Security;

use App\Models\User;
use App\Services\Observability\SecurityLogger;
use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function __construct(private SecurityLogger $security) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->security->record(
            event: 'logout',
            description: 'تسجيل خروج',
            user: $event->user,
            properties: ['guard' => $event->guard],
        );
    }
}
