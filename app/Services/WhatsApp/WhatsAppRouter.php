<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppPurpose;
use App\Exceptions\WhatsAppApiException;
use App\Models\WhatsAppContact;
use App\Services\WhatsApp\Meta\MetaTemplate;

/**
 * Decides how a message goes out: which provider, and whether it must be a template.
 *
 * Provider is resolved per purpose so a project can, for example, run bulk campaigns
 * through Evolution (cheap) while OTP goes through Meta (official and reliable).
 */
class WhatsAppRouter
{
    public function __construct(
        private WhatsAppSettingsService $settings,
        private CustomerServiceWindow $window,
    ) {}

    /**
     * The provider that should handle this purpose. `inherit` resolves to the global one.
     */
    public function providerFor(WhatsAppPurpose $purpose): string
    {
        $configured = $this->routing($purpose)['provider'] ?? 'inherit';
        $global = $this->settings->getSettings()['whatsapp_provider'];

        if ($configured === 'inherit' || ! in_array($configured, WhatsAppSettingsService::SUPPORTED_PROVIDERS, true)) {
            return $global;
        }

        return $configured;
    }

    public function templateFor(WhatsAppPurpose $purpose): ?MetaTemplate
    {
        $template = $this->routing($purpose)['template'] ?? [];
        $name = $template['name'] ?? null;

        if (! is_string($name) || $name === '') {
            return null;
        }

        return new MetaTemplate($name, (string) ($template['language'] ?? 'ar'));
    }

    public function capabilitiesFor(WhatsAppPurpose $purpose): ProviderCapabilities
    {
        return ProviderCapabilities::for($this->providerFor($purpose));
    }

    /**
     * Whether this send must go out as a template rather than free text.
     *
     * Three cases, in order:
     *
     *  - The provider is not bound by the window (Evolution): free text, always.
     *  - It is bound and we can see the window: template only once it has closed.
     *  - It is bound but the window is invisible (Flaxxa — no inbound webhook): fall
     *    back to who started the conversation. Business-initiated sends (OTP, password
     *    reset, campaigns) go to people who have almost certainly not written to us, so
     *    they need a template. A manual reply is the admin answering an existing thread,
     *    so it goes as text — and if the window really had closed, the provider's own
     *    rejection surfaces verbatim, which beats silently rewriting the message.
     */
    public function requiresTemplate(WhatsAppPurpose $purpose, ?WhatsAppContact $contact): bool
    {
        $capabilities = $this->capabilitiesFor($purpose);

        if (! $capabilities->enforcesServiceWindow) {
            return false;
        }

        if ($capabilities->windowIsUnobservable()) {
            return $purpose->isBusinessInitiated();
        }

        return ! $this->window->isOpen($contact);
    }

    /**
     * Resolve the outgoing shape for a purpose, or explain why it cannot be sent.
     *
     * Returning a clear failure beats letting Meta reject the call: the error surfaces
     * at the point the operator can fix it, naming the purpose that needs a template.
     *
     * @return array{mode: 'text'|'template', template: ?MetaTemplate}
     *
     * @throws WhatsAppApiException
     */
    public function resolveSendMode(WhatsAppPurpose $purpose, ?WhatsAppContact $contact): array
    {
        if (! $this->requiresTemplate($purpose, $contact)) {
            return ['mode' => 'text', 'template' => null];
        }

        $template = $this->templateFor($purpose);

        if (! $template) {
            // Naming the actual reason matters: "the window is closed" would be a lie on
            // a provider where the window can never be observed. Both branches still name
            // the purpose, since that is what the operator has to go and configure.
            $reason = $this->capabilitiesFor($purpose)->windowIsUnobservable()
                ? 'هذا المزوّد لا يُبلّغنا بالرسائل الواردة، فلا يمكن معرفة حالة نافذة الـ24 ساعة'
                : 'نافذة المحادثة مغلقة مع هذا الرقم';

            throw new WhatsAppApiException(
                $reason.'، ولم يُضبط قالب معتمد لغرض «'.$purpose->label()
                    .'». اضبط اسم القالب في إعدادات واتساب.',
                0,
                null,
                ['purpose' => $purpose->value, 'reason' => 'template_required'],
            );
        }

        return ['mode' => 'template', 'template' => $template];
    }

    /**
     * @return array<string, mixed>
     */
    protected function routing(WhatsAppPurpose $purpose): array
    {
        $stored = $this->settings->getRoutingOverrides()[$purpose->value] ?? [];
        $default = config("whatsapp.routing.{$purpose->value}", []);

        return [
            'provider' => $stored['provider'] ?? $default['provider'] ?? 'inherit',
            'template' => [
                'name' => $stored['template']['name'] ?? $default['template']['name'] ?? null,
                'language' => $stored['template']['language'] ?? $default['template']['language'] ?? 'ar',
            ],
        ];
    }
}
