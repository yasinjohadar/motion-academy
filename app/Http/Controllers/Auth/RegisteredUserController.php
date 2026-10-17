<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PanelRedirector;
use App\Support\PhoneInput;
use App\Support\PhoneNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'countries' => config('phone.countries'),
            'defaultCountry' => (string) config('phone.default_country'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'country_code' => ['required', 'string', 'regex:/^\+?\d{1,4}$/'],
            'phone' => ['required', 'string', 'max:20'],
        ], [], [
            'country_code' => 'مفتاح الدولة',
            'phone' => 'رقم الهاتف',
        ]);

        // A unique, canonical number: it is how the account is found for WhatsApp
        // sign-in and password recovery.
        $phone = PhoneInput::canonical($request->input('country_code'), $request->input('phone'));

        $user = User::create([
            'name' => $request->name,
            'name_ar' => $request->name,
            'name_en' => $request->name,
            'email' => $request->email,
            'phone' => PhoneNumber::format($phone),
            'phone_e164' => $phone,
            'password' => $request->password,
        ]);

        // Public sign-ups are learners.
        $user->assignRole(Role::findOrCreate('student', 'web'));

        event(new Registered($user));

        Auth::login($user);

        return redirect(PanelRedirector::url());
    }
}
