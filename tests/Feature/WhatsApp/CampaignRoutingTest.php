<?php

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Modules\WhatsApp\Jobs\BroadcastWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\EvolutionInstance;
use App\Modules\WhatsApp\Models\WhatsAppBroadcast;
use App\Modules\WhatsApp\Models\WhatsAppBroadcastRecipient;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\BroadcastWhatsAppMessage;
use App\Modules\WhatsApp\Services\SendWhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Campaigns send through the synchronous path, which used to pass no purpose at all.
 * Everything downstream then treated a marketing blast as a manual reply: the routing
 * matrix's `campaign` row was ignored, and on the official providers the message went
 * out as free text — which Meta rejects for anyone who has not written in first.
 */
class CampaignRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(): WhatsAppSettingsService
    {
        return app(WhatsAppSettingsService::class);
    }

    protected function useFlaxxa(): void
    {
        $this->settings()->updateSettings([
            'whatsapp_provider' => 'flaxxa',
            'flaxxa_base_url' => 'https://wapi.flaxxa.com',
            'flaxxa_token' => 'brand-token',
        ]);
    }

    protected function fakeFlaxxa(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'success', 'message_id' => 1, 'message_wamid' => 'wamid.X',
        ])]);
    }

    protected function runBroadcastJob(): WhatsAppBroadcast
    {
        $broadcast = WhatsAppBroadcast::query()->create([
            'message_template' => 'عرض اليوم',
            'status' => WhatsAppBroadcast::STATUS_PENDING,
            'total_recipients' => 1,
            'created_by' => User::factory()->create()->id,
        ]);

        WhatsAppBroadcastRecipient::query()->create([
            'broadcast_id' => $broadcast->id,
            'phone' => '966511111111',
            'status' => WhatsAppBroadcastRecipient::STATUS_PENDING,
        ]);

        (new BroadcastWhatsAppMessageJob($broadcast, '966511111111', 'عرض اليوم'))
            ->handle(app(SendWhatsAppMessage::class), app(BroadcastWhatsAppMessage::class));

        return $broadcast;
    }

    // ------------------------------------------------------------------ the fix

    public function test_a_campaign_uses_its_own_routing_row_not_the_global_provider(): void
    {
        $this->settings()->updateSettings(['whatsapp_provider' => 'evolution']);
        $this->settings()->updateRoutingOverrides([
            'campaign' => ['provider' => 'flaxxa', 'template' => ['name' => 'promo_ar', 'language' => 'ar']],
        ]);
        $this->settings()->updateSettings([
            'flaxxa_base_url' => 'https://wapi.flaxxa.com',
            'flaxxa_token' => 'brand-token',
        ]);
        $this->fakeFlaxxa();

        $this->runBroadcastJob();

        // Global provider is Evolution; the campaign row says Flaxxa. The row wins.
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'wapi.flaxxa.com'));
        $this->assertSame('flaxxa', WhatsAppMessage::query()->latest('id')->first()->provider);
    }

    public function test_a_campaign_on_an_official_provider_goes_out_as_its_approved_template(): void
    {
        $this->useFlaxxa();
        $this->settings()->updateRoutingOverrides([
            'campaign' => ['provider' => 'inherit', 'template' => ['name' => 'promo_ar', 'language' => 'ar']],
        ]);
        $this->fakeFlaxxa();

        $this->runBroadcastJob();

        Http::assertSent(function (Request $request): bool {
            // Free text here would be rejected by Meta for every recipient who has not
            // messaged the business first — which is most of a campaign list.
            return str_contains($request->url(), '/api/v1/sendtemplatemessage')
                && $request['template_name'] === 'promo_ar';
        });
    }

    public function test_a_campaign_without_a_template_fails_loudly_instead_of_being_rejected_upstream(): void
    {
        $this->useFlaxxa();
        $this->fakeFlaxxa();

        $broadcast = $this->runBroadcastJob();

        // The operator sees a fixable message here rather than a silent rejection
        // from Meta after the whole list has been burned through.
        $this->assertSame(1, $broadcast->fresh()->failed_count);

        $recipient = WhatsAppBroadcastRecipient::query()->first();
        $this->assertSame(WhatsAppBroadcastRecipient::STATUS_FAILED, $recipient->status);
        $this->assertStringContainsString('قالب معتمد', (string) $recipient->error_message);
    }

    // ------------------------------------------------------------------ control

    public function test_campaigns_on_evolution_still_send_plain_text(): void
    {
        $this->settings()->updateSettings([
            'whatsapp_provider' => 'evolution',
            'evolution_base_url' => 'https://evo.example.com',
            'evolution_api_key' => 'key',
            'evolution_instance_name' => 'main',
        ]);

        EvolutionInstance::query()->create([
            'instance_name' => 'main',
            'connection_status' => 'open',
            'rotation_enabled' => true,
            'evolution_base_url' => 'https://evo.example.com',
        ]);

        Http::fake([
            // The rotation service verifies the instance is live before using it.
            '*/instance/connectionState/*' => Http::response(['instance' => ['state' => 'open']]),
            '*' => Http::response(['key' => ['id' => 'EVO1']]),
        ]);

        $this->runBroadcastJob();

        // Evolution has no template concept; the fix must not have changed it.
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sendText'));
    }

    public function test_a_manual_send_is_still_treated_as_manual(): void
    {
        $this->useFlaxxa();
        $this->fakeFlaxxa();

        // No purpose passed — the admin answering a thread must keep free text, which
        // is exactly the case the campaign fix must not swallow.
        app(SendWhatsAppMessage::class)->sendTextSync('966511111111', 'تفضّل');

        // Free-text endpoint, not the template one — the manual path is untouched.
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/sendmessage'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'sendtemplatemessage'));
    }
}
