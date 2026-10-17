@extends('frontend.layouts.student')

@section('sd_title', 'الملف الشخصي')
@section('sd_subtitle', 'بياناتك ورقم واتساب الذي تدخل وتستعيد كلمة مرورك به.')

@php
    $countryCode = old('country_code', $phone['country_code'] ?: $defaultCountry);
@endphp

@section('student')
    <div class="sd-grid-2 is-wide-first">
        <section class="sd-panel">
            <header class="sd-panel-head">
                <h2><i class="ri-user-line"></i> بياناتي</h2>
            </header>

            <form method="post" action="{{ route('student.profile.update') }}" class="sd-form" novalidate>
                @csrf
                @method('patch')

                <label class="sd-field">
                    <span>الاسم الكامل</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" class="@error('name') is-invalid @enderror">
                    @error('name')<small class="sd-error">{{ $message }}</small>@enderror
                </label>

                <label class="sd-field">
                    <span>البريد الإلكتروني</span>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email" dir="ltr" class="@error('email') is-invalid @enderror">
                    @error('email')<small class="sd-error">{{ $message }}</small>@enderror
                    @if ($user->email_verified_at === null)
                        <small class="sd-hint is-warning"><i class="ri-error-warning-line"></i> بريدك غير مؤكد.</small>
                    @endif
                </label>

                <div class="sd-field">
                    <span class="d-flex align-items-center gap-2">
                        رقم واتساب
                        @if ($user->phone_e164)
                            @if ($user->phone_verified_at)
                                <span class="sd-pill is-approved"><i class="ri-shield-check-line"></i> مؤكد</span>
                            @else
                                <span class="sd-pill is-pending">غير مؤكد</span>
                            @endif
                        @endif
                    </span>
                    <div class="sd-phone" dir="ltr">
                        <select name="country_code" aria-label="مفتاح الدولة">
                            @foreach ($countries as $country)
                                <option value="{{ $country['code'] }}" @selected($countryCode === $country['code'])>{{ $country['flag'] }} +{{ $country['code'] }}</option>
                            @endforeach
                        </select>
                        <input type="tel" name="phone" inputmode="numeric" autocomplete="tel-national" placeholder="991234567"
                            value="{{ old('phone', $phone['phone']) }}" class="@error('phone') is-invalid @enderror">
                    </div>
                    @error('phone')<small class="sd-error">{{ $message }}</small>@enderror
                    <small class="sd-hint">يُستخدم للدخول برمز واتساب ولاستعادة كلمة المرور. تغييره يلغي تأكيده حتى أول دخول برمز.</small>
                </div>

                <button type="submit" class="sd-btn"><i class="ri-save-line"></i> حفظ التغييرات</button>
            </form>
        </section>

        <section class="sd-panel">
            <header class="sd-panel-head">
                <h2><i class="ri-lock-password-line"></i> كلمة المرور</h2>
            </header>

            @if (session()->has(\App\Services\Auth\StudentImpersonation::SESSION_KEY))
                <div class="sd-flash is-error" role="note"><i class="ri-lock-line"></i> تغيير كلمة المرور غير متاح أثناء الدخول إلى الحساب من الإدارة.</div>
            @endif
            @error('current_password')
                @if (session()->has(\App\Services\Auth\StudentImpersonation::SESSION_KEY))
                    <div class="sd-flash is-error" role="alert"><i class="ri-error-warning-line"></i> {{ $message }}</div>
                @endif
            @enderror

            @if (session('status') === 'password-updated')
                <div class="sd-flash" role="status"><i class="ri-checkbox-circle-line"></i> تم تغيير كلمة المرور.</div>
            @endif

            <form method="post" action="{{ route('password.update') }}" class="sd-form" novalidate>
                @csrf
                @method('put')

                <label class="sd-field">
                    <span>كلمة المرور الحالية</span>
                    <input type="password" name="current_password" autocomplete="current-password" class="@error('current_password') is-invalid @enderror">
                    @error('current_password')<small class="sd-error">{{ $message }}</small>@enderror
                </label>
                <label class="sd-field">
                    <span>كلمة المرور الجديدة</span>
                    <input type="password" name="password" autocomplete="new-password" class="@error('password') is-invalid @enderror">
                    @error('password')<small class="sd-error">{{ $message }}</small>@enderror
                </label>
                <label class="sd-field">
                    <span>تأكيد كلمة المرور</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password">
                </label>

                <button type="submit" class="sd-btn sd-btn-ghost"><i class="ri-key-2-line"></i> تغيير كلمة المرور</button>
            </form>
        </section>
    </div>
@endsection
