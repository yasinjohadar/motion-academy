{{--
    Shown on every public page while an admin is inside a student's account
    (App\Services\Auth\StudentImpersonation), so it is never mistaken for a
    normal session.
--}}
@php($impersonator = session(\App\Services\Auth\StudentImpersonation::SESSION_KEY))

@if ($impersonator && auth()->check())
    <div class="imp-banner" role="status">
        <span class="imp-banner-icon"><i class="ri-spy-line"></i></span>
        <span class="imp-banner-text">
            أنت داخل حساب الطالب <strong>{{ auth()->user()->name }}</strong>
            <small>بواسطة الإدارة ({{ $impersonator['name'] ?? '' }}) — ما تراه هو ما يراه الطالب.</small>
        </span>
        <form method="post" action="{{ route('impersonate.leave') }}">
            @csrf
            <button type="submit" class="imp-banner-exit"><i class="ri-logout-box-r-line"></i> إنهاء الجلسة</button>
        </form>
    </div>
@endif
