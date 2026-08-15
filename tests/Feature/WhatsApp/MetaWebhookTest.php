<?php

namespace Tests\Feature\WhatsApp;

use App\Jobs\ProcessWhatsAppWebhookEventJob;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\Meta\MetaWebhookParser;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MetaWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $appSecret = 'meta-app-secret-value';

    protected string $verifyToken = 'my-verify-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(WhatsAppSettingsService::class)->updateSettings([
            'meta_app_secret' => $this->appSecret,
            'meta_verify_token' => $this->verifyToken,
        ]);
    }

    protected function signedPost(array $payload, ?string $secret = null)
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret ?? $this->appSecret);

        return $this->call(
            'POST',
            '/api/webhooks/meta',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature],
            $body,
        );
    }

    protected function inboundPayload(string $id = 'wamid.IN1'): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA_ID',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '966500000000', 'phone_number_id' => '109'],
                        'contacts' => [['profile' => ['name' => 'أحمد'], 'wa_id' => '966511111111']],
                        'messages' => [[
                            'from' => '966511111111',
                            'id' => $id,
                            'timestamp' => '1755250000',
                            'type' => 'text',
                            'text' => ['body' => 'السلام عليكم'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    // ------------------------------------------------------------------ verification

    public function test_the_handshake_echoes_the_challenge_for_a_valid_token(): void
    {
        $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token='.$this->verifyToken.'&hub_challenge=1234567')
            ->assertOk()
            ->assertSee('1234567', escape: false);
    }

    public function test_the_handshake_rejects_a_wrong_token(): void
    {
        $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=1234567')
            ->assertForbidden();
    }

    public function test_the_handshake_rejects_a_wrong_mode(): void
    {
        $this->get('/api/webhooks/meta?hub_mode=unsubscribe&hub_verify_token='.$this->verifyToken.'&hub_challenge=1')
            ->assertForbidden();
    }

    public function test_the_handshake_is_refused_when_no_verify_token_is_configured(): void
    {
        app(WhatsAppSettingsService::class)->updateSettings(['meta_verify_token' => '']);

        // An empty configured token must never match an empty supplied token.
        $this->get('/api/webhooks/meta?hub_mode=subscribe&hub_verify_token=&hub_challenge=1')
            ->assertForbidden();
    }

    // ------------------------------------------------------------------ signature

    public function test_a_correctly_signed_delivery_is_accepted(): void
    {
        Queue::fake();

        $this->signedPost($this->inboundPayload())->assertOk();

        $this->assertDatabaseHas('whatsapp_webhook_events', ['provider' => 'meta']);
        Queue::assertPushed(ProcessWhatsAppWebhookEventJob::class);
    }

    public function test_a_delivery_signed_with_the_wrong_secret_is_rejected(): void
    {
        Queue::fake();

        $this->signedPost($this->inboundPayload(), secret: 'attacker-secret')->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_events', 0);
        Queue::assertNothingPushed();
    }

    public function test_a_delivery_without_a_signature_header_is_rejected(): void
    {
        $this->postJson('/api/webhooks/meta', $this->inboundPayload())->assertUnauthorized();

        $this->assertDatabaseCount('whatsapp_webhook_events', 0);
    }

    public function test_a_malformed_signature_header_is_rejected(): void
    {
        $body = json_encode($this->inboundPayload());

        $this->call('POST', '/api/webhooks/meta', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'md5=abc'],
            $body,
        )->assertUnauthorized();
    }

    public function test_deliveries_are_refused_entirely_when_no_app_secret_is_configured(): void
    {
        app(WhatsAppSettingsService::class)->updateSettings(['meta_app_secret' => '']);

        // This endpoint is public; unsigned traffic must never be trusted.
        $this->signedPost($this->inboundPayload(), secret: '')->assertUnauthorized();
    }

    public function test_a_repeated_delivery_is_not_processed_twice(): void
    {
        Queue::fake();

        $this->signedPost($this->inboundPayload())->assertOk();
        WhatsAppWebhookEvent::query()->first()->markAsProcessed();

        // Meta retries until it sees a 200; the retry must not re-run side effects.
        $this->signedPost($this->inboundPayload())->assertOk();

        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        Queue::assertPushed(ProcessWhatsAppWebhookEventJob::class, 1);
    }

    // ------------------------------------------------------------------ parsing

    public function test_the_parser_reads_an_inbound_text_message(): void
    {
        $parsed = app(MetaWebhookParser::class)->parse($this->inboundPayload('wamid.ABC'));

        $this->assertCount(1, $parsed['messages']);

        $message = $parsed['messages'][0];
        $this->assertSame('wamid.ABC', $message->messageId);
        $this->assertSame('966511111111', $message->from);
        $this->assertSame('text', $message->type);
        $this->assertSame('السلام عليكم', $message->textBody);
    }

    public function test_the_parser_reads_a_status_update_with_conversation_and_pricing(): void
    {
        $parsed = app(MetaWebhookParser::class)->parse([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.OUT1',
                            'status' => 'delivered',
                            'timestamp' => '1755250100',
                            'recipient_id' => '966511111111',
                            'conversation' => ['id' => 'conv1', 'origin' => ['type' => 'service']],
                            'pricing' => ['billable' => true, 'category' => 'service'],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $this->assertCount(1, $parsed['statuses']);

        $status = $parsed['statuses'][0];
        $this->assertSame('wamid.OUT1', $status->messageId);
        $this->assertSame('delivered', $status->status);
        $this->assertSame('service', $status->pricing['category']);
    }

    public function test_the_parser_uses_a_media_caption_as_the_body(): void
    {
        $payload = $this->inboundPayload();
        $payload['entry'][0]['changes'][0]['value']['messages'][0] = [
            'from' => '966511111111', 'id' => 'wamid.IMG', 'timestamp' => '1755250000',
            'type' => 'image', 'image' => ['id' => 'media1', 'caption' => 'صورة الفاتورة'],
        ];

        $parsed = app(MetaWebhookParser::class)->parse($payload);

        // Without this the inbox would show an empty row for every image.
        $this->assertSame('صورة الفاتورة', $parsed['messages'][0]->textBody);
    }

    public function test_the_parser_ignores_changes_for_other_fields(): void
    {
        $parsed = app(MetaWebhookParser::class)->parse([
            'object' => 'whatsapp_business_account',
            'entry' => [['changes' => [['field' => 'account_update', 'value' => ['event' => 'VERIFIED']]]]],
        ]);

        $this->assertSame([], $parsed['messages']);
        $this->assertSame([], $parsed['statuses']);
    }

    public function test_an_inbound_meta_message_is_stored_through_the_job(): void
    {
        // End to end: signed delivery → queued job → message row, using the Meta parser.
        $this->signedPost($this->inboundPayload('wamid.E2E'))->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'meta_message_id' => 'wamid.E2E',
            'direction' => 'inbound',
        ]);
        $this->assertDatabaseHas('whatsapp_contacts', ['wa_id' => '966511111111']);
    }
}
