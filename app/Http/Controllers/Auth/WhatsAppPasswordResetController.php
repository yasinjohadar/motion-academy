<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\Concerns\RunsOtpFlow;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Response;

/**
 * Forgot password → code on WhatsApp → choose a new password.
 *
 * A correct code is exchanged for a standard password-reset token, and the user
 * continues on the normal "new password" screen; nothing about changing the
 * password is reimplemented here, and no password is ever sent over WhatsApp.
 */
class WhatsAppPasswordResetController extends Controller
{
    use RunsOtpFlow;

    protected function purpose(): OtpPurpose
    {
        return OtpPurpose::PasswordReset;
    }

    protected function verifyRoute(): string
    {
        return 'password.whatsapp.verify';
    }

    protected function startRoute(): string
    {
        return 'password.request';
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->sendCode($request);
    }

    public function verify(Request $request): Response|RedirectResponse
    {
        return $this->verifyScreen($request, 'أدخل رمز الاستعادة', 'password.whatsapp.check', 'password.whatsapp.resend');
    }

    public function resend(Request $request): RedirectResponse
    {
        return $this->resendCode($request);
    }

    public function check(Request $request): RedirectResponse
    {
        $user = $this->verifiedUser($request);

        if (! $user) {
            return redirect()->route($this->startRoute());
        }

        $token = Password::broker()->createToken($user);

        return redirect()->route('password.reset', ['token' => $token, 'email' => $user->email]);
    }
}
