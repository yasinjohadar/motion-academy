<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\WhatsAppPurpose;
use App\Exceptions\WhatsAppApiException;
use App\Models\WhatsAppContact;
use App\Services\WhatsApp\ProviderCapabilities;
use App\Services\WhatsApp\WhatsAppRouter;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The window rules, which used to be one hardcoded `$provider !== 'meta'` check.
 *
 * Half of this file is a control: Evolution and Meta must behave exactly as they did
 * before the capability layer existed. A refactor that quietly changes them would be
 * worse than the duplication it removed.
 */
class ProviderCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(): WhatsAppSettingsService
    {
        return app(WhatsAppSettingsService::class);
    }

    protected function router(): WhatsAppRouter
    {
        return app(WhatsAppRouter::class);
    }

    protected function useProvider(string $provider): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => $provider]);
    }

    /**
     * Unsaved on purpose: the router only reads `last_inbound_at`, and persisting would
     * collide on the unique `wa_id` when a test needs several window states.
     */
    protected function contact(mixed $lastInbound): WhatsAppContact
    {
        return new WhatsAppContact([
            'wa_id' => '966511111111',
            'last_inbound_at' => $lastInbound,
        ]);
    }

    // ------------------------------------------------------------------ the map

    public function test_the_capability_map_matches_each_provider_reality(): void
    {
        $evolution = ProviderCapabilities::for('evolution');
        $meta = ProviderCapabilities::for('meta');
        $flaxxa = ProviderCapabilities::for('flaxxa');

        $this->assertFalse($evolution->enforcesServiceWindow, 'Evolution is unofficial — no window.');
        $this->assertTrue($meta->enforcesServiceWindow);
        $this->assertTrue($flaxxa->enforcesServiceWindow, 'Flaxxa runs on Meta Cloud API.');

        $this->assertTrue($meta->tracksInboundWindow);
        $this->assertFalse($flaxxa->tracksInboundWindow, 'Flaxxa documents no inbound webhook.');

        $this->assertTrue($evolution->requiresConnectedInstance);
        $this->assertFalse($flaxxa->requiresConnectedInstance);

        $this->assertFalse($flaxxa->pushesDeliveryStatus, 'Status must be polled on Flaxxa.');

        $this->assertTrue($flaxxa->exposesTemplateCatalog);
        $this->assertFalse($evolution->exposesTemplateCatalog, 'Evolution has no template concept.');
    }

    public function test_only_flaxxa_has_an_unobservable_window(): void
    {
        $this->assertTrue(ProviderCapabilities::for('flaxxa')->windowIsUnobservable());
        $this->assertFalse(ProviderCapabilities::for('meta')->windowIsUnobservable());
        $this->assertFalse(ProviderCapabilities::for('evolution')->windowIsUnobservable());
    }

    public function test_an_unknown_provider_falls_back_to_the_permissive_evolution_profile(): void
    {
        // A typo in settings must not start rewriting messages into templates.
        $this->assertFalse(ProviderCapabilities::for('nonsense')->enforcesServiceWindow);
    }

    // ------------------------------------------------------------------ control: evolution

    public function test_evolution_never_requires_a_template(): void
    {
        $this->useProvider('evolution');

        foreach (WhatsAppPurpose::cases() as $purpose) {
            $this->assertFalse(
                $this->router()->requiresTemplate($purpose, $this->contact(null)),
                "Evolution should send {$purpose->value} as free text."
            );
        }
    }

    // ------------------------------------------------------------------ control: meta

    public function test_meta_allows_free_text_inside_the_window(): void
    {
        $this->useProvider('meta');

        $this->assertFalse(
            $this->router()->requiresTemplate(WhatsAppPurpose::Otp, $this->contact(now()->subHour()))
        );
    }

    public function test_meta_requires_a_template_once_the_window_closes(): void
    {
        $this->useProvider('meta');

        $this->assertTrue(
            $this->router()->requiresTemplate(WhatsAppPurpose::Manual, $this->contact(now()->subHours(25)))
        );
    }

    public function test_meta_requires_a_template_for_a_contact_that_never_wrote(): void
    {
        $this->useProvider('meta');

        $this->assertTrue(
            $this->router()->requiresTemplate(WhatsAppPurpose::Manual, $this->contact(null))
        );
    }

    // ------------------------------------------------------------------ flaxxa

    public function test_flaxxa_requires_a_template_for_system_initiated_sends(): void
    {
        $this->useProvider('flaxxa');

        foreach ([WhatsAppPurpose::Otp, WhatsAppPurpose::PasswordReset, WhatsAppPurpose::Campaign] as $purpose) {
            $this->assertTrue(
                $this->router()->requiresTemplate($purpose, $this->contact(null)),
                "{$purpose->value} reaches people who never wrote to us — Meta needs a template."
            );
        }
    }

    public function test_flaxxa_lets_a_manual_reply_go_as_free_text(): void
    {
        $this->useProvider('flaxxa');

        // The admin is answering an open thread. Rewriting that into a template would
        // be wrong, and we have no webhook to prove the window state either way.
        $this->assertFalse(
            $this->router()->requiresTemplate(WhatsAppPurpose::Manual, $this->contact(null))
        );
    }

    public function test_flaxxa_ignores_last_inbound_at_because_it_can_never_be_trusted(): void
    {
        $this->useProvider('flaxxa');

        // Even with a fresh timestamp (written by some other provider's webhook), the
        // decision must not depend on it.
        $this->assertTrue(
            $this->router()->requiresTemplate(WhatsAppPurpose::Otp, $this->contact(now()))
        );
    }

    public function test_flaxxa_explains_the_real_reason_when_no_template_is_configured(): void
    {
        $this->useProvider('flaxxa');

        try {
            $this->router()->resolveSendMode(WhatsAppPurpose::Otp, $this->contact(now()));
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException $e) {
            // "The window is closed" would be a lie here — we cannot see the window.
            $this->assertStringContainsString('لا يُبلّغنا بالرسائل الواردة', $e->getMessage());
            $this->assertStringNotContainsString('نافذة المحادثة مغلقة', $e->getMessage());
        }
    }

    public function test_meta_still_reports_the_closed_window_reason(): void
    {
        $this->useProvider('meta');

        try {
            $this->router()->resolveSendMode(WhatsAppPurpose::Otp, $this->contact(now()->subHours(25)));
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException $e) {
            $this->assertStringContainsString('نافذة المحادثة مغلقة', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ per-purpose routing

    public function test_the_window_rule_follows_the_purpose_override_not_the_global_provider(): void
    {
        $this->useProvider('evolution');
        $this->settings()->updateRoutingOverrides([
            'otp' => ['provider' => 'flaxxa', 'template' => ['name' => null, 'language' => 'ar']],
        ]);

        // Campaigns stay on Evolution (free text) while OTP is bound by Flaxxa's rules.
        $this->assertFalse($this->router()->requiresTemplate(WhatsAppPurpose::Campaign, $this->contact(null)));
        $this->assertTrue($this->router()->requiresTemplate(WhatsAppPurpose::Otp, $this->contact(null)));
    }
}
