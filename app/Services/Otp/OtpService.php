<?php

namespace App\Services\Otp;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Services\Observability\SecurityLogger;
use App\Support\Otp\DeliversOtpCode;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Hash;

/**
 * One-time passcodes.
 *
 * Security posture:
 *  - codes are stored hashed, never in clear text
 *  - comparison goes through Hash::check, which is constant-time
 *  - a new code invalidates the previous one for that identifier + purpose
 *  - attempts are capped per code, and issuing is rate limited per identifier
 *  - every issue / success / failure lands on the security timeline
 *
 * Delivery is not this class's business. The transport named in `config('otp.channel')`
 * carries the code, so issuing and verifying work the same whether codes go out over
 * WhatsApp, SMS or email — and the core needs no module installed to own the feature.
 */
class OtpService
{
    public function __construct(
        private SecurityLogger $security,
        private RateLimiter $limiter,
    ) {}

    public function length(): int
    {
        return (int) config('otp.length', 6);
    }

    public function ttlMinutes(): int
    {
        return (int) config('otp.ttl_minutes', 10);
    }

    public function maxAttempts(): int
    {
        return (int) config('otp.max_attempts', 5);
    }

    /**
     * Issue and deliver a code.
     *
     * @throws OtpThrottledException
     */
    public function issue(string $identifier, OtpPurpose $purpose): OtpCode
    {
        $this->ensureNotThrottled($identifier, $purpose);

        // Only one live code per identifier + purpose: leaving old ones valid would
        // multiply the guessing surface.
        OtpCode::query()->for($identifier, $purpose)->active()->update(['consumed_at' => now()]);

        $code = $this->generateCode();

        $record = OtpCode::query()->create([
            'identifier' => $identifier,
            'purpose' => $purpose->value,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($this->ttlMinutes()),
            'ip' => request()->ip(),
        ]);

        $this->deliver($identifier, $purpose, $code);

        $this->security->record(
            event: 'otp_issued',
            description: 'إصدار رمز تحقق',
            properties: ['identifier' => $this->mask($identifier), 'purpose' => $purpose->value],
        );

        return $record;
    }

    /**
     * Verify a submitted code. Consumes it on success.
     */
    public function verify(string $identifier, OtpPurpose $purpose, string $code): bool
    {
        $record = OtpCode::query()
            ->for($identifier, $purpose)
            ->active()
            ->latest('id')
            ->first();

        if (! $record) {
            $this->recordFailure($identifier, $purpose, 'no_active_code');

            return false;
        }

        if ($record->attempts >= $this->maxAttempts()) {
            // Burn it: unlimited guesses against a 6-digit code is the whole risk.
            $record->update(['consumed_at' => now()]);
            $this->recordFailure($identifier, $purpose, 'too_many_attempts');

            return false;
        }

        $record->increment('attempts');

        if (! Hash::check($code, $record->code_hash)) {
            $this->recordFailure($identifier, $purpose, 'mismatch');

            return false;
        }

        $record->update(['consumed_at' => now()]);
        $this->limiter->clear($this->throttleKey($identifier, $purpose));

        $this->security->record(
            event: 'otp_verified',
            description: 'تحقق ناجح من رمز',
            properties: ['identifier' => $this->mask($identifier), 'purpose' => $purpose->value],
        );

        return true;
    }

    protected function deliver(string $identifier, OtpPurpose $purpose, string $code): void
    {
        $this->channel()->deliver($identifier, $purpose, $code, $this->ttlMinutes());
    }

    /**
     * The configured transport, resolved from config rather than injected, so the core
     * never names the module that carries the code.
     *
     * @throws OtpDeliveryException when nothing is configured to deliver
     */
    protected function channel(): DeliversOtpCode
    {
        $class = config('otp.channel');

        if (is_string($class) && class_exists($class)) {
            $instance = app($class);

            if ($instance instanceof DeliversOtpCode) {
                return $instance;
            }
        }

        // Failing loudly beats recording an issued code that nothing will ever send —
        // the user would sit waiting for a message that was never dispatched.
        throw new OtpDeliveryException(
            'لا توجد قناة توصيل مُهيّأة لرموز التحقق. اضبط otp.channel.'
        );
    }

    protected function generateCode(): string
    {
        $length = $this->length();

        // random_int is cryptographically secure; rand()/mt_rand() are not.
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * @throws OtpThrottledException
     */
    protected function ensureNotThrottled(string $identifier, OtpPurpose $purpose): void
    {
        $key = $this->throttleKey($identifier, $purpose);
        $max = (int) config('otp.max_per_window', 3);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $seconds = $this->limiter->availableIn($key);

            $this->security->record(
                event: 'otp_throttled',
                description: 'تجاوز حد طلب رموز التحقق',
                properties: ['identifier' => $this->mask($identifier), 'purpose' => $purpose->value],
            );

            throw new OtpThrottledException(
                "تم طلب رموز كثيرة. حاول بعد {$seconds} ثانية.",
                $seconds
            );
        }

        $this->limiter->hit($key, (int) config('otp.window_seconds', 600));
    }

    protected function throttleKey(string $identifier, OtpPurpose $purpose): string
    {
        return 'otp:'.$purpose->value.':'.mb_strtolower($identifier).':'.(request()->ip() ?? 'cli');
    }

    protected function recordFailure(string $identifier, OtpPurpose $purpose, string $reason): void
    {
        $this->security->record(
            event: 'otp_failed',
            description: 'فشل التحقق من رمز',
            properties: [
                'identifier' => $this->mask($identifier),
                'purpose' => $purpose->value,
                'reason' => $reason,
            ],
        );
    }

    /**
     * Security logs are widely readable; a full phone number does not belong there.
     */
    protected function mask(string $identifier): string
    {
        $length = mb_strlen($identifier);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).mb_substr($identifier, -4);
    }
}
