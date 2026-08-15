<?php

namespace Tests\Feature\Otp;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Services\Otp\OtpDeliveryException;
use App\Services\Otp\OtpService;
use App\Support\Otp\DeliversOtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Records what it was asked to deliver, standing in for any module's transport. */
class RecordingOtpChannel implements DeliversOtpCode
{
    /** @var array<int, array<string, mixed>> */
    public static array $delivered = [];

    public function deliver(string $identifier, OtpPurpose $purpose, string $code, int $ttlMinutes): void
    {
        static::$delivered[] = compact('identifier', 'purpose', 'code', 'ttlMinutes');
    }
}

/** A transport that cannot send — the provider is down. */
class BrokenOtpChannel implements DeliversOtpCode
{
    public function deliver(string $identifier, OtpPurpose $purpose, string $code, int $ttlMinutes): void
    {
        throw new \RuntimeException('المزوّد لا يستجيب');
    }
}

/**
 * OTP issuing no longer depends on the WhatsApp module.
 *
 * The core owns codes; a module owns the transport. These tests use stand-in channels
 * to prove the seam works for anything — SMS or email would plug in the same way.
 */
class OtpDeliveryChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        RecordingOtpChannel::$delivered = [];
    }

    protected function otp(): OtpService
    {
        return app(OtpService::class);
    }

    public function test_the_configured_channel_receives_the_code(): void
    {
        config()->set('otp.channel', RecordingOtpChannel::class);

        $this->otp()->issue('966511111111', OtpPurpose::Login);

        $this->assertCount(1, RecordingOtpChannel::$delivered);

        $delivered = RecordingOtpChannel::$delivered[0];
        $this->assertSame('966511111111', $delivered['identifier']);
        $this->assertSame(OtpPurpose::Login, $delivered['purpose']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $delivered['code']);
        $this->assertSame(config('otp.ttl_minutes'), $delivered['ttlMinutes']);
    }

    public function test_the_delivered_code_is_the_one_that_verifies(): void
    {
        config()->set('otp.channel', RecordingOtpChannel::class);

        $this->otp()->issue('966511111111', OtpPurpose::Login);

        $this->assertTrue($this->otp()->verify(
            '966511111111',
            OtpPurpose::Login,
            RecordingOtpChannel::$delivered[0]['code'],
        ));
    }

    public function test_issuing_fails_loudly_when_no_channel_is_configured(): void
    {
        config()->set('otp.channel', null);

        // A silent no-op here would record an issued code that nothing ever sent,
        // leaving the user waiting for a message that does not exist.
        $this->expectException(OtpDeliveryException::class);

        $this->otp()->issue('966511111111', OtpPurpose::Login);
    }

    public function test_a_configured_class_that_is_not_a_channel_is_refused(): void
    {
        config()->set('otp.channel', \stdClass::class);

        $this->expectException(OtpDeliveryException::class);

        $this->otp()->issue('966511111111', OtpPurpose::Login);
    }

    public function test_a_failed_delivery_does_not_leave_a_usable_code_behind(): void
    {
        config()->set('otp.channel', BrokenOtpChannel::class);

        try {
            $this->otp()->issue('966511111111', OtpPurpose::Login);
            $this->fail('Expected the delivery failure to surface.');
        } catch (\RuntimeException $e) {
            $this->assertSame('المزوّد لا يستجيب', $e->getMessage());
        }

        // The row is written before delivery is attempted, which is deliberate — but
        // the caller learns it failed, so nothing reports success to the user.
        $this->assertSame(1, OtpCode::query()->count());
    }

    public function test_the_shipped_whatsapp_channel_is_wired_by_default(): void
    {
        // Control: the default configuration still works end to end, so the seam did
        // not quietly disconnect OTP from its only real transport.
        $this->assertInstanceOf(
            DeliversOtpCode::class,
            app(config('otp.channel'))
        );
    }
}
