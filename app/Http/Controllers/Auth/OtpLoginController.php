<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\Concerns\RunsOtpFlow;
use App\Http\Controllers\Controller;
use App\Support\PanelRedirector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sign in with a one-time code sent to the account's phone on WhatsApp.
 */
class OtpLoginController extends Controller
{
    use RunsOtpFlow;

    protected function purpose(): OtpPurpose
    {
        return OtpPurpose::Login;
    }

    protected function verifyRoute(): string
    {
        return 'login.otp.verify';
    }

    protected function startRoute(): string
    {
        return 'login.otp';
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->ensureAvailable()) {
            return $redirect;
        }

        return Inertia::render('Auth/OtpLogin', [
            'countries' => config('phone.countries'),
            'defaultCountry' => (string) config('phone.default_country'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->sendCode($request);
    }

    public function verify(Request $request): Response|RedirectResponse
    {
        return $this->verifyScreen($request, 'أدخل رمز الدخول', 'login.otp.check', 'login.otp.resend');
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

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(PanelRedirector::url());
    }
}
