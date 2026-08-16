<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppContact;

/**
 * Meta's 24-hour customer service window.
 *
 * The window opens when the contact messages the business. While it is open, free-form
 * text is allowed. Once it closes, Meta accepts only pre-approved templates — free text
 * is rejected outright.
 *
 * Evolution has no such rule, so this only gates the Meta path.
 */
class CustomerServiceWindow
{
    public function hours(): int
    {
        return (int) config('whatsapp.customer_service_window_hours', 24);
    }

    public function isOpen(?WhatsAppContact $contact): bool
    {
        $lastInbound = $contact?->last_inbound_at;

        if ($lastInbound === null) {
            // Never heard from them — the window has never opened.
            return false;
        }

        return $lastInbound->greaterThan(now()->subHours($this->hours()));
    }

    /**
     * Remaining minutes before the window closes, or null when already closed.
     */
    public function minutesRemaining(?WhatsAppContact $contact): ?int
    {
        if (! $this->isOpen($contact)) {
            return null;
        }

        return (int) now()->diffInMinutes(
            $contact->last_inbound_at->copy()->addHours($this->hours()),
            absolute: true
        );
    }

    public function markInbound(WhatsAppContact $contact): void
    {
        $contact->forceFill(['last_inbound_at' => now()])->save();
    }
}
