{{--
    The student dashboard frame: a gradient header, then a sidebar (a scrolling
    tab strip on phones) beside the page.

    Pages set:  @section('sd_title'), @section('sd_subtitle'), optional @section('sd_actions'),
                and their body in @section('student').
    $account comes from FrontendServiceProvider (name, initial, unread, pending, adminUrl).
--}}
@extends('frontend.layouts.master')

@section('body_class', 'student-page')

@php
    $sdNav = [
        ['route' => 'student.dashboard', 'match' => 'student.dashboard', 'icon' => 'ri-dashboard-3-line', 'label' => 'نظرة عامة'],
        ['route' => 'student.courses', 'match' => 'student.courses', 'icon' => 'ri-book-open-line', 'label' => 'دوراتي'],
        ['route' => 'student.requests', 'match' => 'student.requests', 'icon' => 'ri-file-list-3-line', 'label' => 'الطلبات والاشتراكات', 'badge' => $account['pending'] ?? 0],
        ['route' => 'student.notifications', 'match' => 'student.notifications', 'icon' => 'ri-notification-3-line', 'label' => 'الإشعارات', 'badge' => $account['unread'] ?? 0],
        ['route' => 'student.profile', 'match' => 'student.profile*', 'icon' => 'ri-user-settings-line', 'label' => 'الملف الشخصي'],
    ];
@endphp

@section('content')
    <section class="sd-hero">
        <div class="container">
            <div class="sd-hero-inner">
                <div>
                    <span class="sd-hero-kicker"><i class="ri-graduation-cap-line"></i> لوحة الطالب</span>
                    <h1 class="sd-hero-title">@yield('sd_title')</h1>
                    @hasSection('sd_subtitle')
                        <p class="sd-hero-text">@yield('sd_subtitle')</p>
                    @endif
                </div>
                @hasSection('sd_actions')
                    <div class="sd-hero-actions">@yield('sd_actions')</div>
                @endif
            </div>
        </div>
    </section>

    <div class="container sd-shell">
        <aside class="sd-side" aria-label="أقسام لوحة الطالب">
            <div class="sd-profile">
                <span class="sd-avatar" aria-hidden="true">{{ $account['initial'] ?? '؟' }}</span>
                <div class="sd-profile-text">
                    <strong>{{ $account['name'] ?? '' }}</strong>
                    <small class="en-text">{{ $account['email'] ?? '' }}</small>
                </div>
            </div>

            <nav class="sd-nav">
                @foreach ($sdNav as $item)
                    @php($isActive = request()->routeIs($item['match']))
                    <a href="{{ route($item['route']) }}" class="sd-nav-link{{ $isActive ? ' is-active' : '' }}" @if ($isActive) aria-current="page" @endif>
                        <i class="{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                        @if (! empty($item['badge']))
                            <span class="sd-nav-badge en-text">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="sd-side-foot">
                <a href="{{ route('courses') }}" class="sd-nav-link"><i class="ri-compass-3-line"></i><span>تصفّح الدورات</span></a>
                @if (! empty($account['adminUrl']))
                    <a href="{{ $account['adminUrl'] }}" class="sd-nav-link"><i class="ri-shield-user-line"></i><span>لوحة الإدارة</span></a>
                @endif
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sd-nav-link is-danger"><i class="ri-logout-box-r-line"></i><span>تسجيل الخروج</span></button>
                </form>
            </div>
        </aside>

        <main class="sd-main" id="sd-main">
            @if (session('student_success'))
                <div class="sd-flash" role="status"><i class="ri-checkbox-circle-line"></i> {{ session('student_success') }}</div>
            @endif
            @error('enrollment')
                <div class="sd-flash is-error" role="alert"><i class="ri-error-warning-line"></i> {{ $message }}</div>
            @enderror

            @yield('student')
        </main>
    </div>
@endsection

@push('scripts')
    <script>
        // On phones the sections are a scrolling strip: bring the current one into view.
        (function () {
            var active = document.querySelector('.sd-side .sd-nav-link.is-active');
            var strip = active && active.parentElement;
            if (strip && strip.scrollWidth > strip.clientWidth) {
                strip.scrollLeft = active.offsetLeft - (strip.clientWidth - active.offsetWidth) / 2;
            }
        })();
    </script>
@endpush
