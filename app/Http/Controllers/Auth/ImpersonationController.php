<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\StudentImpersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The browser side of "open this student's account": the one-time link signs
 * this browser in as the student, and "exit" signs it out again.
 */
class ImpersonationController extends Controller
{
    public function __construct(private readonly StudentImpersonation $impersonation) {}

    public function enter(Request $request, string $token): RedirectResponse
    {
        $grant = $this->impersonation->redeem($token);

        if (! $grant) {
            return redirect()->route('login')
                ->with('status', 'رابط الدخول غير صالح: انتهت صلاحيته أو استُخدم من قبل. اطلب رابطاً جديداً.');
        }

        // Whoever was signed in on this browser is signed out first.
        if (Auth::check()) {
            Auth::logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Tells LogSuccessfulLogin this is not the student signing in themselves.
        $request->attributes->set('impersonation', true);
        Auth::login($grant['student']);
        $request->session()->regenerate();

        $request->session()->put(StudentImpersonation::SESSION_KEY, [
            'id' => $grant['admin']->id,
            'name' => $grant['admin']->name,
        ]);

        return redirect()->route('student.dashboard');
    }

    public function leave(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
