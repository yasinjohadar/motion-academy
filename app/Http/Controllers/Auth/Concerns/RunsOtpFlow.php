<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Otp\OtpDeliveryException;
use App\Services\Otp\OtpService;
use App\Services\Otp\OtpSettings;
use App\Services\Otp\OtpThrottledException;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The phone → WhatsApp code → verify steps shared by OTP login and WhatsApp
 * password reset.
 *
 * Two rules hold throughout:
 *  - The answer never reveals whether a number has an account: an unknown number
 *    gets the same "if it is registered, a code is on its way" screen.
 *  - The number being verified lives in the session, never in the posted form, so
 *    a code cannot be checked against a number other than the one it was sent to.
 */
trait RunsOtpFlow
{
    abstract protected function purpose(): OtpPurpose;

    /** Route name of the verify screen for this flow. */
    abstract protected function verifyRoute(): string;

    /** Route name of the phone-entry screen for this flow. */
    abstract protected function startRoute(): string;

    protected function sessionKey(): string
    {
        return 'otp_flow.'.$this->purpose()->value;
    }

    protected function ensureAvailable(): ?RedirectResponse
    {
        if (app(OtpSettings::class)->isAvailable($this->purpose())) {
            return null;
        }

        return redirect()->route('login')->with('status', 'هذه الخدمة غير متاحة حالياً. استخدم البريد الإلكتروني وكلمة المرور.');
    }

    /**
     * Validate the number, issue a code if it belongs to an account, and move to
     * the verify screen either way.
     */
    protected function sendCode(Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAvailable()) {
            return $redirect;
        }

        $phone = $this->validatedPhone($request);
        $user = User::query()->where('phone_e164', $phone)->first();

        if ($user) {
            try {
                app(OtpService::class)->issue($phone, $this->purpose());
            } catch (OtpThrottledException $e) {
                throw ValidationException::withMessages([
                    'phone' => "طلبت رموزاً كثيرة. حاول بعد {$this->humanSeconds($e->retryAfterSeconds)}.",
                ]);
            } catch (OtpDeliveryException) {
                throw ValidationException::withMessages([
                    'phone' => 'تعذّر إرسال الرمز عبر واتساب حالياً. حاول لاحقاً أو استخدم البريد الإلكتروني.',
                ]);
            }
        }

        $request->session()->put($this->sessionKey(), [
            'phone' => $phone,
            'user_id' => $user?->id,
            'sent_at' => now()->timestamp,
        ]);

        return redirect()->route($this->verifyRoute());
    }

    protected function verifyScreen(Request $request, string $title, string $submitRoute, string $resendRoute): Response|RedirectResponse
    {
        if ($redirect = $this->ensureAvailable()) {
            return $redirect;
        }

        $flow = $request->session()->get($this->sessionKey());
        if (! $flow) {
            return redirect()->route($this->startRoute());
        }

        return Inertia::render('Auth/OtpVerify', [
            'purpose' => $this->purpose()->value,
            'title' => $title,
            'maskedPhone' => PhoneNumber::mask($flow['phone']),
            'length' => app(OtpService::class)->length(),
            'resendIn' => $this->resendIn($flow),
            'submitUrl' => route($submitRoute),
            'resendUrl' => route($resendRoute),
            'changeUrl' => route($this->startRoute()),
            'status' => $request->session()->get('status'),
        ]);
    }

    protected function resendCode(Request $request): RedirectResponse
    {
        $flow = $request->session()->get($this->sessionKey());
        if (! $flow) {
            return redirect()->route($this->startRoute());
        }

        if (($wait = $this->resendIn($flow)) > 0) {
            return back()->withErrors(['code' => "يمكنك طلب رمز جديد بعد {$this->humanSeconds($wait)}."]);
        }

        if ($flow['user_id']) {
            try {
                app(OtpService::class)->issue($flow['phone'], $this->purpose());
            } catch (OtpThrottledException $e) {
                return back()->withErrors(['code' => "طلبت رموزاً كثيرة. حاول بعد {$this->humanSeconds($e->retryAfterSeconds)}."]);
            } catch (OtpDeliveryException) {
                return back()->withErrors(['code' => 'تعذّر إرسال الرمز عبر واتساب حالياً. حاول لاحقاً.']);
            }
        }

        $request->session()->put($this->sessionKey().'.sent_at', now()->timestamp);

        return back()->with('status', 'أرسلنا رمزاً جديداً — تحقّق من واتساب.');
    }

    /**
     * Check the code; on success return the user (and forget the flow).
     *
     * @throws ValidationException
     */
    protected function verifiedUser(Request $request): ?User
    {
        $flow = $request->session()->get($this->sessionKey());
        if (! $flow) {
            return null;
        }

        $code = (string) Validator::make(
            $request->only('code'),
            ['code' => ['required', 'string', 'regex:/^\d{4,8}$/']],
            ['code.regex' => 'أدخل الرمز المكوّن من أرقام فقط.'],
            ['code' => 'الرمز'],
        )->validate()['code'];

        $user = $flow['user_id'] ? User::query()->find($flow['user_id']) : null;

        if (! $user || $user->phone_e164 !== $flow['phone'] || ! app(OtpService::class)->verify($flow['phone'], $this->purpose(), $code)) {
            throw ValidationException::withMessages(['code' => 'الرمز غير صحيح أو انتهت صلاحيته.']);
        }

        $request->session()->forget($this->sessionKey());

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return $user;
    }

    protected function validatedPhone(Request $request): string
    {
        $data = Validator::make(
            $request->only('country_code', 'phone'),
            [
                'country_code' => ['required', 'string', 'regex:/^\+?\d{1,4}$/'],
                'phone' => ['required', 'string', 'max:20'],
            ],
            [],
            ['country_code' => 'مفتاح الدولة', 'phone' => 'رقم الهاتف'],
        )->validate();

        $phone = PhoneNumber::normalize($data['country_code'], $data['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'رقم الهاتف غير صحيح. تأكد من مفتاح الدولة والرقم.']);
        }

        return $phone;
    }

    /**
     * @param  array{sent_at?: int}  $flow
     */
    protected function resendIn(array $flow): int
    {
        $elapsed = now()->timestamp - (int) ($flow['sent_at'] ?? 0);

        return max(0, app(OtpSettings::class)->resendCooldown() - $elapsed);
    }

    protected function humanSeconds(int $seconds): string
    {
        return $seconds >= 60 ? ceil($seconds / 60).' دقيقة' : max(1, $seconds).' ثانية';
    }
}
