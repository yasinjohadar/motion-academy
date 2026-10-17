<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Otp\OtpSettings;
use App\Support\Otp\DeliversOtpCode;
use App\Support\PhoneInput;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Control panel → OTP: turn WhatsApp sign-in / password reset on or off, word the
 * message, set limits, and send a test code to check delivery end to end.
 */
class OtpSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private readonly OtpSettings $settings) {}

    public static function middleware(): array
    {
        return [
            (new Middleware('permission:admin.otp-settings.list|admin.otp-settings.edit'))->only(['edit']),
            (new Middleware('permission:admin.otp-settings.edit'))->only(['update', 'testSend']),
        ];
    }

    public function edit(Request $request): Response
    {
        $usersWithPhone = User::query()->whereNotNull('phone_e164')->count();

        return Inertia::render('Admin/Settings/Otp', [
            'settings' => $this->settings->all(),
            'defaultTemplate' => OtpSettings::DEFAULT_TEMPLATE,
            'readiness' => [
                'channel' => $this->settings->channelReady(),
                'users_with_phone' => $usersWithPhone,
                'users_total' => User::query()->count(),
            ],
            'countries' => config('phone.countries'),
            'defaultCountry' => (string) config('phone.default_country'),
            'myPhone' => PhoneInput::split($request->user()->phone_e164),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_enabled' => ['required', 'boolean'],
            'reset_enabled' => ['required', 'boolean'],
            'message_template' => ['required', 'string', 'max:500', 'regex:/\{code\}/'],
            'ttl_minutes' => ['required', 'integer', 'min:1', 'max:30'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'resend_cooldown_seconds' => ['required', 'integer', 'min:15', 'max:600'],
        ], [
            'message_template.regex' => 'يجب أن يحتوي نص الرسالة على {code} ليظهر فيه الرمز.',
        ], [
            'message_template' => 'نص الرسالة',
            'ttl_minutes' => 'مدة الصلاحية',
            'max_attempts' => 'عدد المحاولات',
            'resend_cooldown_seconds' => 'فترة إعادة الإرسال',
        ]);

        $this->settings->update($data);

        return back()->with('success', 'تم حفظ إعدادات رموز التحقق.');
    }

    /**
     * Deliver a sample code straight through the configured channel. No code is
     * stored: this checks the transport, not the sign-in flow.
     */
    public function testSend(Request $request): RedirectResponse
    {
        $request->validate([
            'country_code' => ['required', 'string', 'regex:/^\+?\d{1,4}$/'],
            'phone' => ['required', 'string', 'max:20'],
        ], [], ['phone' => 'رقم الهاتف']);

        $phone = PhoneNumber::normalize($request->input('country_code'), $request->input('phone'));
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'رقم الهاتف غير صحيح.']);
        }

        if (! $this->settings->channelReady()) {
            return back()->with('error', 'لا توجد قناة إرسال مضبوطة (وحدة واتساب غير مثبّتة).');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            /** @var DeliversOtpCode $channel */
            $channel = app((string) config('otp.channel'));
            $channel->deliver($phone, OtpPurpose::Login, $code, $this->settings->ttlMinutes());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'تعذّر الإرسال: '.$e->getMessage());
        }

        return back()->with('success', 'أُرسل رمز تجريبي ('.$code.') إلى '.PhoneNumber::format($phone).'. تحقّق من واتساب — قد يتأخر قليلاً إن كان عامل الطوابير متوقفاً.');
    }
}
