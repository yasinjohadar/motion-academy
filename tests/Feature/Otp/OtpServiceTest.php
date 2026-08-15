<?php

namespace Tests\Feature\Otp;

use App\Enums\OtpPurpose;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\OtpCode;
use App\Models\WhatsAppMessage;
use App\Services\Observability\SecurityLogger;
use App\Services\Otp\OtpService;
use App\Services\Otp\OtpThrottledException;
use App\Services\WhatsApp\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $phone = '966511111111';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();   // delivery is exercised separately; here we test the codes
        app(WhatsAppSettingsService::class)->updateSettings(['whatsapp_provider' => 'evolution']);
    }

    protected function otp(): OtpService
    {
        return app(OtpService::class);
    }

    /**
     * Reads the clear-text code out of the delivered message, the way a user would.
     */
    protected function issueAndCapture(OtpPurpose $purpose = OtpPurpose::Login): string
    {
        $this->otp()->issue($this->phone, $purpose);

        $body = WhatsAppMessage::query()->latest('id')->first()->body;

        preg_match('/(\d{6})/', $body, $matches);

        return $matches[1] ?? '';
    }

    // ------------------------------------------------------------------ storage

    public function test_the_code_is_never_stored_in_clear_text(): void
    {
        $code = $this->issueAndCapture();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $stored = DB::table('otp_codes')->pluck('code_hash')->implode('|');

        // A leaked table must not hand out working codes.
        $this->assertStringNotContainsString($code, $stored);
        $this->assertStringStartsWith('$2y$', $stored);
    }

    public function test_the_code_hash_is_hidden_from_serialisation(): void
    {
        $this->otp()->issue($this->phone, OtpPurpose::Login);

        $this->assertArrayNotHasKey('code_hash', OtpCode::query()->first()->toArray());
    }

    // ------------------------------------------------------------------ verifying

    public function test_a_correct_code_verifies_once(): void
    {
        $code = $this->issueAndCapture();

        $this->assertTrue($this->otp()->verify($this->phone, OtpPurpose::Login, $code));

        // Replay must fail — the code is consumed.
        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::Login, $code));
    }

    public function test_a_wrong_code_fails(): void
    {
        $this->issueAndCapture();

        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::Login, '000000'));
    }

    public function test_a_code_is_scoped_to_its_purpose(): void
    {
        $code = $this->issueAndCapture(OtpPurpose::Login);

        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::PasswordReset, $code));
        $this->assertTrue($this->otp()->verify($this->phone, OtpPurpose::Login, $code));
    }

    public function test_a_code_is_scoped_to_its_identifier(): void
    {
        $code = $this->issueAndCapture();

        $this->assertFalse($this->otp()->verify('966599999999', OtpPurpose::Login, $code));
    }

    public function test_an_expired_code_fails(): void
    {
        $code = $this->issueAndCapture();

        $this->travel(11)->minutes();   // default TTL is 10

        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::Login, $code));
    }

    public function test_issuing_a_new_code_invalidates_the_previous_one(): void
    {
        $first = $this->issueAndCapture();
        $second = $this->issueAndCapture();

        $this->assertNotSame($first, $second);

        // Leaving old codes alive would multiply the guessing surface.
        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::Login, $first));
        $this->assertTrue($this->otp()->verify($this->phone, OtpPurpose::Login, $second));
    }

    public function test_attempts_are_capped_and_the_code_is_burned(): void
    {
        $code = $this->issueAndCapture();

        for ($i = 0; $i < 5; $i++) {
            $this->otp()->verify($this->phone, OtpPurpose::Login, '111111');
        }

        // Even the right code is dead once the cap is hit — otherwise a 6-digit
        // secret could be brute forced.
        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::Login, $code));
    }

    public function test_verifying_without_any_issued_code_fails_cleanly(): void
    {
        $this->assertFalse($this->otp()->verify($this->phone, OtpPurpose::Login, '123456'));
    }

    // ------------------------------------------------------------------ throttling

    public function test_issuing_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->otp()->issue($this->phone, OtpPurpose::Login);
        }

        $this->expectException(OtpThrottledException::class);

        $this->otp()->issue($this->phone, OtpPurpose::Login);
    }

    public function test_the_throttle_reports_how_long_to_wait(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->otp()->issue($this->phone, OtpPurpose::Login);
        }

        try {
            $this->otp()->issue($this->phone, OtpPurpose::Login);
            $this->fail('Expected OtpThrottledException.');
        } catch (OtpThrottledException $e) {
            $this->assertGreaterThan(0, $e->retryAfterSeconds);
        }
    }

    public function test_a_different_number_is_not_throttled(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->otp()->issue($this->phone, OtpPurpose::Login);
        }

        $this->otp()->issue('966522222222', OtpPurpose::Login);

        $this->assertSame(4, OtpCode::query()->count());
    }

    // ------------------------------------------------------------------ audit

    public function test_issuing_and_verifying_land_on_the_security_timeline(): void
    {
        $code = $this->issueAndCapture();
        $this->otp()->verify($this->phone, OtpPurpose::Login, $code);
        $this->otp()->verify($this->phone, OtpPurpose::Login, 'wrong1');

        $events = Activity::query()
            ->where('log_name', SecurityLogger::LOG_NAME)
            ->pluck('event')
            ->all();

        $this->assertContains('otp_issued', $events);
        $this->assertContains('otp_verified', $events);
        $this->assertContains('otp_failed', $events);
    }

    public function test_the_security_log_masks_the_phone_number(): void
    {
        $this->otp()->issue($this->phone, OtpPurpose::Login);

        $properties = Activity::query()->where('event', 'otp_issued')->first()->properties;

        // Security logs are widely readable; a full number does not belong there.
        $this->assertSame('********1111', $properties['identifier']);
        $this->assertStringNotContainsString($this->phone, json_encode($properties));
    }

    public function test_the_security_log_never_contains_the_code(): void
    {
        $code = $this->issueAndCapture();

        $raw = DB::table('activity_log')->pluck('properties')->implode('');

        $this->assertStringNotContainsString($code, $raw);
    }

    // ------------------------------------------------------------------ delivery

    public function test_the_code_is_delivered_with_the_otp_purpose(): void
    {
        $this->otp()->issue($this->phone, OtpPurpose::Login);

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job): bool {
            // The purpose is what makes Meta switch to the authentication template
            // when the customer service window is closed.
            return ($job->messageData['purpose'] ?? null) === 'otp'
                && ($job->messageData['template_parameters'][0] ?? null) !== null;
        });
    }
}
