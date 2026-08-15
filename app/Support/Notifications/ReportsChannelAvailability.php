<?php

namespace App\Support\Notifications;

/**
 * Lets a notification channel answer for its own readiness.
 *
 * Without this the core has to know how every channel decides it can deliver — which
 * meant NotificationChannelResolver importing four WhatsApp classes to work out
 * whether a provider was configured. A channel supplied by a module is the only place
 * that knowledge belongs, and keeping it there is what lets the module be removed.
 *
 * A channel that does not implement this is treated as available once registered.
 */
interface ReportsChannelAvailability
{
    /**
     * Can this channel deliver to this notifiable right now?
     *
     * Must not throw — a misconfigured provider is an unavailable channel, not a
     * failed notification.
     */
    public function isAvailableFor(object $notifiable): bool;

    /**
     * Why not — shown on the preferences screen so a user is told rather than left
     * with a toggle that silently does nothing. Null when the channel is available.
     */
    public function unavailableReasonFor(object $notifiable): ?string;
}
