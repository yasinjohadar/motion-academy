<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\WhatsApp\Enums\WhatsAppPurpose;
use App\Modules\WhatsApp\Jobs\PollFlaxxaMessageStatusJob;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppOutboundSendService;
use App\Modules\WhatsApp\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FlaxxaStatusPollingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(WhatsAppSettingsService::class)->updateSettings([
            'whatsapp_provider' => 'flaxxa',
            'flaxxa_base_url' => 'https://wapi.flaxxa.com',
            'flaxxa_token' => 'brand-token',
        ]);
    }

    protected function message(?string $status = null): WhatsAppMessage
    {
        $contact = WhatsAppContact::query()->create(['wa_id' => '918470968883']);

        return WhatsAppMessage::query()->create([
            'direction' => WhatsAppMessage::DIRECTION_OUTBOUND,
            'contact_id' => $contact->id,
            'type' => WhatsAppMessage::TYPE_TEXT,
            'body' => 'مرحباً',
            'status' => $status ?? WhatsAppMessage::STATUS_QUEUED,
        ]);
    }

    protected function fakeSend(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'message_id' => 28341,
                'message_wamid' => 'wamid.HBgM123',
            ]),
        ]);
    }

    protected function send(WhatsAppMessage $message): void
    {
        app(WhatsAppOutboundSendService::class)->send($message, [
            'type' => 'text',
            'text' => 'مرحباً',
            'purpose' => WhatsAppPurpose::Manual->value,
        ]);
    }

    // ------------------------------------------------------------------ the provider column

    public function test_a_sent_message_records_which_provider_carried_it(): void
    {
        Queue::fake();
        $this->fakeSend();

        $message = $this->message();
        $this->send($message);

        // Reads the column after a real send rather than just asserting it exists —
        // the write goes through update(), so a missing $fillable entry would leave
        // this null with no error anywhere.
        $this->assertSame('flaxxa', $message->fresh()->provider);
    }

    public function test_the_flaxxa_numeric_id_survives_in_the_payload(): void
    {
        Queue::fake();
        $this->fakeSend();

        $message = $this->message();
        $this->send($message);

        $this->assertSame(28341, $message->fresh()->payload['response']['message_id']);
    }

    // ------------------------------------------------------------------ scheduling

    public function test_a_flaxxa_send_schedules_a_status_poll(): void
    {
        Queue::fake();
        $this->fakeSend();

        $message = $this->message();
        $this->send($message);

        Queue::assertPushed(PollFlaxxaMessageStatusJob::class, function ($job) use ($message): bool {
            return $job->message->id === $message->id
                && $job->flaxxaMessageId === 28341;
        });
    }

    public function test_polling_can_be_switched_off_for_large_campaigns(): void
    {
        Queue::fake();
        config()->set('whatsapp.flaxxa.poll_status', false);
        $this->fakeSend();

        $this->send($this->message());

        // Each poll costs one of 60 requests per minute; a big campaign must be able
        // to opt out entirely.
        Queue::assertNotPushed(PollFlaxxaMessageStatusJob::class);
    }

    public function test_other_providers_never_schedule_a_poll(): void
    {
        Queue::fake();
        app(WhatsAppSettingsService::class)->updateSettings([
            'whatsapp_provider' => 'meta',
            'meta_phone_number_id' => '123',
            'meta_access_token' => 'tok',
        ]);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.X']]])]);

        $message = $this->message();
        // Meta needs an open window for free text, so give the contact a recent inbound.
        $message->contact->forceFill(['last_inbound_at' => now()->subMinutes(5)])->save();

        $this->send($message);

        // Meta pushes status over its webhook — polling it would be pure waste.
        Queue::assertNotPushed(PollFlaxxaMessageStatusJob::class);
    }

    // ------------------------------------------------------------------ the job

    public function test_the_job_applies_a_delivered_status(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['value' => 'delivered']])]);

        $message = $this->message(WhatsAppMessage::STATUS_SENT);

        (new PollFlaxxaMessageStatusJob($message, 28341))->handle(app(WhatsAppSettingsService::class));

        $this->assertSame(WhatsAppMessage::STATUS_DELIVERED, $message->fresh()->status);
    }

    public function test_a_failed_status_carries_the_reason(): void
    {
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => [
            'value' => 'failed', 'error_details' => 'Recipient not on WhatsApp',
        ]])]);

        $message = $this->message(WhatsAppMessage::STATUS_SENT);

        (new PollFlaxxaMessageStatusJob($message, 28341))->handle(app(WhatsAppSettingsService::class));

        $fresh = $message->fresh();
        $this->assertSame(WhatsAppMessage::STATUS_FAILED, $fresh->status);
        $this->assertSame('Recipient not on WhatsApp', $fresh->error['message']);
    }

    public function test_a_final_state_stops_the_chain(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['value' => 'read']])]);

        (new PollFlaxxaMessageStatusJob($this->message(WhatsAppMessage::STATUS_SENT), 28341))
            ->handle(app(WhatsAppSettingsService::class));

        Queue::assertNotPushed(PollFlaxxaMessageStatusJob::class);
    }

    public function test_a_non_final_state_schedules_another_look(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['value' => 'sent']])]);

        (new PollFlaxxaMessageStatusJob($this->message(WhatsAppMessage::STATUS_SENT), 28341))
            ->handle(app(WhatsAppSettingsService::class));

        Queue::assertPushed(PollFlaxxaMessageStatusJob::class, fn ($job): bool => $job->attempt === 2);
    }

    public function test_it_gives_up_after_the_configured_attempts(): void
    {
        Queue::fake();
        config()->set('whatsapp.flaxxa.status_poll_max_attempts', 3);
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['value' => 'sent']])]);

        (new PollFlaxxaMessageStatusJob($this->message(WhatsAppMessage::STATUS_SENT), 28341, attempt: 3))
            ->handle(app(WhatsAppSettingsService::class));

        // Otherwise an undeliverable message would be polled forever.
        Queue::assertNotPushed(PollFlaxxaMessageStatusJob::class);
    }

    public function test_a_message_already_final_is_not_polled_at_all(): void
    {
        Http::fake();

        (new PollFlaxxaMessageStatusJob($this->message(WhatsAppMessage::STATUS_READ), 28341))
            ->handle(app(WhatsAppSettingsService::class));

        Http::assertNothingSent();
    }

    public function test_a_polling_error_never_marks_the_message_failed(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Too Many Attempts.'], 429)]);

        $message = $this->message(WhatsAppMessage::STATUS_SENT);

        (new PollFlaxxaMessageStatusJob($message, 28341))->handle(app(WhatsAppSettingsService::class));

        // The send succeeded; only our question about it failed. Conflating the two
        // would show delivered messages as failed whenever the quota runs out.
        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
        Queue::assertPushed(PollFlaxxaMessageStatusJob::class);
    }

    public function test_an_unknown_status_value_is_ignored_rather_than_guessed(): void
    {
        Queue::fake();
        Http::fake(['*' => Http::response(['status' => 'success', 'data' => ['value' => 'quantum']])]);

        $message = $this->message(WhatsAppMessage::STATUS_SENT);

        (new PollFlaxxaMessageStatusJob($message, 28341))->handle(app(WhatsAppSettingsService::class));

        $this->assertSame(WhatsAppMessage::STATUS_SENT, $message->fresh()->status);
    }
}
