<?php

namespace App\Http\Controllers\Frontend\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfileUpdateRequest;
use App\Services\Seo\SeoPage;
use App\Support\PhoneInput;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student edits their account on the public site. Same rules as the admin
 * profile: a changed email or phone loses its verification, a phone may belong
 * to one account only. The password form posts to the shared `password.update`.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('frontend.student.profile', [
            'seo' => SeoPage::make('الملف الشخصي')->noindex(follow: false),
            'user' => $user,
            'phone' => PhoneInput::split($user->phone_e164),
            'countries' => config('phone.countries'),
            'defaultCountry' => (string) config('phone.default_country'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $user->fill([
            'name' => $data['name'],
            'name_ar' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->filled('phone')) {
            $canonical = PhoneInput::canonical($request->input('country_code'), $request->input('phone'), $user->id);

            if ($canonical !== $user->phone_e164) {
                $user->forceFill([
                    'phone' => PhoneNumber::format($canonical),
                    'phone_e164' => $canonical,
                    'phone_verified_at' => null,
                ]);
            }
        }

        $user->save();

        return redirect()->route('student.profile')->with('student_success', 'تم حفظ بياناتك.');
    }
}
