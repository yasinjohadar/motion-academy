<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\WhatsApp\Enums\WhatsAppPurpose;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\CustomerServiceWindow;
use App\Modules\WhatsApp\Services\WhatsAppOutboundSendService;
use App\Modules\WhatsApp\Services\WhatsAppRouter;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurposeRoutingTest extends TestCase
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

    protected function configureMeta(): void
    {
        $this->settings()->updateSettings([
            'whatsapp_provider' => 'meta',
            'meta_phone_number_id' => '109',
            'meta_access_token' => 'token',
            'meta_api_version' => 'v21.0',
        ]);
    }

    protected function contact(?string $lastInbound = null): WhatsAppContact
    {
        $contact = WhatsAppContact::findOrCreateByWaId('966511111111');

        if ($lastInbound) {
            $contact->forceFill(['last_inbound_at' => $lastInbound])->save();
        }

        return $contact->fresh();
    }

    protected function message(WhatsAppContact $contact): WhatsAppMessage
    {
        return WhatsAppMessage::query()->create([
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => 'رمزك 123456',
            'status' => WhatsAppMessage::STATUS_QUEUED,
        ]);
    }

    protected function fakeMeta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.OK']],
        ], 200)]);
    }

    // ------------------------------------------------------------------ provider choice

    public function test_inherit_follows_the_global_provider(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'meta']);

        $this->assertSame('meta', $this->router()->providerFor(WhatsAppPurpose::Campaign));
    }

    public function test_a_purpose_can_override_the_global_provider(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'evolution']);
        $this->settings()->updateRoutingOverrides([
            'otp' => ['provider' => 'meta'],
        ]);

        // The point of per-purpose routing: bulk on Evolution, OTP on official Meta.
        $this->assertSame('evolution', $this->router()->providerFor(WhatsAppPurpose::Campaign));
        $this->assertSame('meta', $this->router()->providerFor(WhatsAppPurpose::Otp));
    }

    public function test_an_invalid_override_falls_back_to_the_global_provider(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'evolution']);
        $this->settings()->updateRoutingOverrides(['otp' => ['provider' => 'pigeon']]);

        $this->assertSame('evolution', $this->router()->providerFor(WhatsAppPurpose::Otp));
    }

    // ------------------------------------------------------------------ the window

    public function test_the_window_is_closed_for_a_contact_who_never_wrote(): void
    {
        $this->assertFalse(app(CustomerServiceWindow::class)->isOpen($this->contact()));
    }

    public function test_the_window_is_open_within_twenty_four_hours(): void
    {
        $window = app(CustomerServiceWindow::class);

        $this->assertTrue($window->isOpen($this->contact(now()->subHours(2)->toDateTimeString())));
        $this->assertFalse($window->isOpen($this->contact(now()->subHours(25)->toDateTimeString())));
    }

    public function test_evolution_never_requires_a_template(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'evolution']);

        // Evolution is unofficial and has no window rule.
        $this->assertFalse(
            $this->router()->requiresTemplate(WhatsAppPurpose::Otp, $this->contact())
        );
    }

    public function test_meta_requires_a_template_once_the_window_closes(): void
    {
        $this->configureMeta();

        $this->assertTrue(
            $this->router()->requiresTemplate(WhatsAppPurpose::Otp, $this->contact(now()->subHours(30)->toDateTimeString()))
        );
        $this->assertFalse(
            $this->router()->requiresTemplate(WhatsAppPurpose::Otp, $this->contact(now()->subHour()->toDateTimeString()))
        );
    }

    // ------------------------------------------------------------------ sending

    public function test_free_text_is_sent_while_the_window_is_open(): void
    {
        $this->configureMeta();
        $this->fakeMeta();

        $contact = $this->contact(now()->subHour()->toDateTimeString());

        app(WhatsAppOutboundSendService::class)->send($this->message($contact), [
            'type' => 'text', 'text' => 'رمزك 123456', 'purpose' => 'otp',
        ]);

        Http::assertSent(fn (Request $r) => $r->data()['type'] === 'text');
    }

    public function test_a_closed_window_switches_the_send_to_the_configured_template(): void
    {
        $this->configureMeta();
        $this->settings()->updateRoutingOverrides([
            'otp' => ['provider' => 'meta', 'template' => ['name' => 'otp_code', 'language' => 'ar']],
        ]);
        $this->fakeMeta();

        $contact = $this->contact(now()->subDays(3)->toDateTimeString());

        app(WhatsAppOutboundSendService::class)->send($this->message($contact), [
            'type' => 'text', 'text' => '123456', 'purpose' => 'otp',
        ]);

        Http::assertSent(function (Request $r): bool {
            $body = $r->data();

            return $body['type'] === 'template'
                && $body['template']['name'] === 'otp_code'
                && $body['template']['language']['code'] === 'ar'
                && $body['template']['components'][0]['parameters'][0]['text'] === '123456';
        });
    }

    public function test_a_closed_window_without_a_template_fails_with_an_actionable_message(): void
    {
        $this->configureMeta();
        $this->fakeMeta();

        $contact = $this->contact(now()->subDays(3)->toDateTimeString());

        try {
            app(WhatsAppOutboundSendService::class)->send($this->message($contact), [
                'type' => 'text', 'text' => '123456', 'purpose' => 'otp',
            ]);
            $this->fail('Expected WhatsAppApiException.');
        } catch (WhatsAppApiException $e) {
            // Fail where the operator can fix it, rather than letting Meta reject it.
            $this->assertStringContainsString('رموز التحقق', $e->getMessage());
            $this->assertSame('template_required', $e->getDetails()['reason']);
            Http::assertNothingSent();
        }
    }

    public function test_the_purpose_selects_the_provider_at_send_time(): void
    {
        $this->settings()->updateSettings([
            'whatsapp_provider' => 'evolution',
            'meta_phone_number_id' => '109',
            'meta_access_token' => 'token',
        ]);
        $this->settings()->updateRoutingOverrides([
            'otp' => ['provider' => 'meta', 'template' => ['name' => 'otp_code', 'language' => 'ar']],
        ]);
        $this->fakeMeta();

        $contact = $this->contact(now()->subDays(3)->toDateTimeString());

        app(WhatsAppOutboundSendService::class)->send($this->message($contact), [
            'type' => 'text', 'text' => '123456', 'purpose' => 'otp',
        ]);

        // Global provider is Evolution, yet this OTP went to Meta.
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'));
    }

    public function test_an_unknown_purpose_falls_back_to_manual(): void
    {
        $this->configureMeta();
        $this->fakeMeta();

        $contact = $this->contact(now()->subHour()->toDateTimeString());

        app(WhatsAppOutboundSendService::class)->send($this->message($contact), [
            'type' => 'text', 'text' => 'x', 'purpose' => 'not-a-purpose',
        ]);

        Http::assertSent(fn (Request $r) => $r->data()['type'] === 'text');
    }

    public function test_an_inbound_message_opens_the_window(): void
    {
        $contact = $this->contact();
        $this->assertNull($contact->last_inbound_at);

        app(CustomerServiceWindow::class)->markInbound($contact);

        $this->assertTrue(app(CustomerServiceWindow::class)->isOpen($contact->fresh()));
    }
}
