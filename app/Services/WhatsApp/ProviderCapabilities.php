<?php

namespace App\Services\WhatsApp;

/**
 * What a provider can and cannot do.
 *
 * This exists because two different questions used to be answered by one string
 * comparison (`$provider !== 'meta'`):
 *
 *   1. Does Meta's 24-hour customer service window apply to this provider?
 *   2. Do we actually receive inbound events, so we can tell whether it is open?
 *
 * They were the same answer for Evolution (no / yes) and Meta (yes / yes), so
 * conflating them was harmless. Flaxxa breaks that: it sits on top of Meta's Cloud
 * API, so the window applies — but it exposes no documented inbound webhook, so we
 * can never observe the window opening. Answering (1) with (2)'s value would turn
 * every message into a template, including an admin's manual reply inside a live
 * conversation.
 */
final class ProviderCapabilities
{
    private function __construct(
        /** Meta's 24-hour window governs this provider; outside it, only templates are accepted. */
        public bool $enforcesServiceWindow,

        /** We receive inbound messages, so `last_inbound_at` reflects reality. */
        public bool $tracksInboundWindow,

        /** Sending needs a paired instance row, not just credentials. */
        public bool $requiresConnectedInstance,

        /** Delivery state arrives on its own; without this it must be polled. */
        public bool $pushesDeliveryStatus,

        /** The provider can list its approved templates, so the UI can offer a dropdown. */
        public bool $exposesTemplateCatalog,
    ) {}

    public static function for(string $provider): self
    {
        return match ($provider) {
            'meta' => new self(
                enforcesServiceWindow: true,
                tracksInboundWindow: true,
                requiresConnectedInstance: false,
                pushesDeliveryStatus: true,
                // Meta exposes a template catalogue too; not wired up yet.
                exposesTemplateCatalog: false,
            ),
            'flaxxa' => new self(
                // Official Cloud API underneath, so Meta's rules apply in full …
                enforcesServiceWindow: true,
                // … but no documented webhook, so the window is unobservable.
                tracksInboundWindow: false,
                requiresConnectedInstance: false,
                pushesDeliveryStatus: false,
                exposesTemplateCatalog: true,
            ),
            // Evolution is unofficial: free text any time, and it webhooks everything.
            default => new self(
                enforcesServiceWindow: false,
                tracksInboundWindow: true,
                requiresConnectedInstance: true,
                pushesDeliveryStatus: true,
                exposesTemplateCatalog: false,
            ),
        };
    }

    /**
     * The provider is bound by the window but cannot observe it, so the decision has
     * to fall back to who started the conversation.
     */
    public function windowIsUnobservable(): bool
    {
        return $this->enforcesServiceWindow && ! $this->tracksInboundWindow;
    }
}
