{{--
    Site navigation — Motion Academy.
      ≥1200px  floating glass bar: logo · links with a sliding indicator · search · theme · account
      <1200px  compact bar: logo · search · theme · menu, the menu opens a side sheet
      <768px   plus a bottom dock with the main destinations
    Behaviour: assets/js/nav.js. Search ids are shared with assets/js/search.js.
--}}
@php
    $navLinks = [
        ['route' => 'home', 'href' => route('home'), 'label' => 'الرئيسية', 'icon' => 'fas fa-house', 'active' => request()->routeIs('home')],
        ['route' => 'courses', 'href' => route('courses'), 'label' => 'الدورات', 'icon' => 'fas fa-play-circle', 'active' => request()->routeIs('courses', 'courses.show')],
        ['route' => 'categories', 'href' => route('categories'), 'label' => 'المجالات', 'icon' => 'fas fa-shapes', 'active' => request()->routeIs('categories')],
        ['route' => 'blog', 'href' => route('blog.index'), 'label' => 'المدونة', 'icon' => 'fas fa-feather-alt', 'active' => request()->routeIs('blog.*')],
        ['route' => 'who-we-are', 'href' => route('who-we-are'), 'label' => 'من نحن', 'icon' => 'fas fa-users', 'active' => request()->routeIs('who-we-are')],
        ['route' => 'about', 'href' => route('about'), 'label' => 'حول', 'icon' => 'fas fa-circle-info', 'active' => request()->routeIs('about')],
    ];
    // Signed in: everyone gets the student dashboard; staff also get the admin panel ($account['adminUrl']).
    $accountUrl = $account ? route('student.dashboard') : route('login');
    $accountLabel = $account ? 'لوحتي' : 'تسجيل الدخول';
    $accountIcon = $account ? 'fas fa-user-graduate' : 'fas fa-user';
    $accountLinks = $account ? array_filter([
        ['href' => route('student.dashboard'), 'icon' => 'ri-dashboard-3-line', 'label' => 'لوحتي'],
        ['href' => route('student.courses'), 'icon' => 'ri-book-open-line', 'label' => 'دوراتي'],
        ['href' => route('student.notifications'), 'icon' => 'ri-notification-3-line', 'label' => 'الإشعارات', 'badge' => $account['unread']],
        ['href' => route('student.requests'), 'icon' => 'ri-file-list-3-line', 'label' => 'طلباتي', 'badge' => $account['pending']],
        ['href' => route('student.profile'), 'icon' => 'ri-user-settings-line', 'label' => 'الملف الشخصي'],
        $account['adminUrl'] ? ['href' => $account['adminUrl'], 'icon' => 'ri-shield-user-line', 'label' => 'لوحة الإدارة'] : null,
    ]) : [];
@endphp

<header class="mnav main-nav" id="siteNavbar">
    <div class="container mnav-container">
        <div class="mnav-bar">
            <a class="mnav-brand site-brand" href="{{ route('home') }}" aria-label="{{ $siteSettings['site_name'] }}">
                <img src="{{ $fa }}/images/logo-mark.png" alt="" class="site-brand-logo" width="46" height="39">
                <span class="site-brand-name">MOTION ACADEMY</span>
                <span class="site-brand-tagline">We turn ideas into life</span>
            </a>

            <nav class="mnav-links" aria-label="القائمة الرئيسية">
                <span class="mnav-indicator" aria-hidden="true"></span>
                @foreach ($navLinks as $link)
                    <a href="{{ $link['href'] }}" class="mnav-link {{ $link['active'] ? 'is-active' : '' }}" @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
                @endforeach
            </nav>

            <div class="mnav-search" id="navSearchMobile">
                <form class="site-search-form" role="search" action="{{ route('courses') }}" method="get">
                    <div class="site-search-box" id="siteSearchBox">
                        <i class="fas fa-search site-search-icon" aria-hidden="true"></i>
                        <input type="search" class="site-search-input" id="nav-search-input" name="search" placeholder="ابحث عن دورة أو مهارة..." autocomplete="off" aria-label="بحث في المنصة" value="{{ request('search') }}">
                        <kbd class="site-search-kbd" aria-hidden="true">Ctrl K</kbd>
                        <button type="submit" class="site-search-submit" aria-label="بحث"><i class="fas fa-arrow-left"></i></button>
                    </div>
                    <div class="site-search-results" id="nav-search-results" hidden></div>
                </form>
            </div>

            <div class="mnav-actions">
                <button type="button" class="mnav-icon mnav-search-btn" data-nav-search aria-label="فتح البحث" aria-expanded="false" aria-controls="navSearchMobile">
                    <i class="fas fa-search"></i>
                </button>

                <button type="button" class="mnav-icon mnav-theme theme-toggle" aria-label="تبديل الوضع الليلي">
                    <i class="fas fa-moon mnav-theme-moon" aria-hidden="true"></i>
                    <i class="fas fa-sun mnav-theme-sun" aria-hidden="true"></i>
                </button>

                @if ($account)
                    <details class="mnav-account" data-nav-account>
                        <summary class="mnav-account-btn" aria-label="حسابي">
                            <span class="mnav-account-avatar">{{ $account['initial'] }}</span>
                            <span class="mnav-account-name">{{ \Illuminate\Support\Str::of($account['name'])->trim()->explode(' ')->first() }}</span>
                            @if ($account['unread'] > 0)
                                <span class="mnav-account-dot" aria-label="{{ $account['unread'] }} إشعار غير مقروء"></span>
                            @endif
                            <i class="fas fa-chevron-down mnav-account-caret"></i>
                        </summary>
                        <div class="mnav-account-menu">
                            <div class="mnav-account-head">
                                <strong>{{ $account['name'] }}</strong>
                                <small class="en-text">{{ $account['email'] }}</small>
                            </div>
                            @foreach ($accountLinks as $item)
                                <a href="{{ $item['href'] }}" class="mnav-account-link">
                                    <i class="{{ $item['icon'] }}"></i>
                                    <span>{{ $item['label'] }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="mnav-account-badge en-text">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                            <form method="post" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="mnav-account-link is-danger"><i class="ri-logout-box-r-line"></i><span>تسجيل الخروج</span></button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ $accountUrl }}" class="mnav-cta site-nav-cta">
                        <i class="{{ $accountIcon }}"></i>
                        <span>{{ $accountLabel }}</span>
                    </a>
                @endif

                <button type="button" class="mnav-burger" data-nav-open aria-label="فتح القائمة" aria-expanded="false" aria-controls="mnavSheet">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </div>
    <span class="mnav-progress" aria-hidden="true"></span>
</header>

{{-- Side sheet (tablet & mobile) --}}
<div class="mnav-sheet" id="mnavSheet" aria-hidden="true">
    <div class="mnav-sheet-backdrop" data-nav-close></div>
    <aside class="mnav-sheet-panel" role="dialog" aria-modal="true" aria-label="القائمة">
        <div class="mnav-sheet-head">
            <a href="{{ route('home') }}" class="mnav-sheet-brand">
                <img src="{{ $fa }}/images/logo-mark.png" alt="" width="40" height="34">
                <span>MOTION ACADEMY</span>
            </a>
            <button type="button" class="mnav-icon" data-nav-close aria-label="إغلاق القائمة"><i class="fas fa-times"></i></button>
        </div>

        <nav class="mnav-sheet-links" aria-label="القائمة">
            @foreach ($navLinks as $index => $link)
                <a href="{{ $link['href'] }}" class="mnav-sheet-link {{ $link['active'] ? 'is-active' : '' }}" style="--i: {{ $index }};">
                    <span class="mnav-sheet-num en-text">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="mnav-sheet-icon"><i class="{{ $link['icon'] }}"></i></span>
                    <span class="mnav-sheet-label">{{ $link['label'] }}</span>
                    <i class="fas fa-arrow-left mnav-sheet-go"></i>
                </a>
            @endforeach
        </nav>

        <div class="mnav-sheet-foot">
            @if ($account)
                <div class="mnav-sheet-account">
                    @foreach ($accountLinks as $item)
                        <a href="{{ $item['href'] }}" class="mnav-sheet-account-link">
                            <i class="{{ $item['icon'] }}"></i> {{ $item['label'] }}
                            @if (! empty($item['badge']))
                                <span class="mnav-account-badge en-text">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="mnav-sheet-ghost w-100"><i class="ri-logout-box-r-line"></i> تسجيل الخروج</button>
                </form>
            @else
                <a href="{{ $accountUrl }}" class="mnav-sheet-cta"><i class="{{ $accountIcon }}"></i> {{ $accountLabel }}</a>
            @endif
            @guest
                <a href="{{ route('register') }}" class="mnav-sheet-ghost">إنشاء حساب جديد</a>
            @endguest
            <p class="mnav-sheet-tag">We turn ideas into life</p>
        </div>
    </aside>
</div>

{{-- Bottom dock (mobile) --}}
<nav class="mnav-dock" aria-label="تنقل سريع">
    @foreach ([$navLinks[0], $navLinks[1]] as $link)
        <a href="{{ $link['href'] }}" class="mnav-dock-item {{ $link['active'] ? 'is-active' : '' }}">
            <i class="{{ $link['icon'] }}"></i><span>{{ $link['label'] }}</span>
        </a>
    @endforeach
    <button type="button" class="mnav-dock-play" data-nav-search aria-label="بحث">
        <i class="fas fa-search"></i>
    </button>
    <a href="{{ $navLinks[3]['href'] }}" class="mnav-dock-item {{ $navLinks[3]['active'] ? 'is-active' : '' }}">
        <i class="{{ $navLinks[3]['icon'] }}"></i><span>{{ $navLinks[3]['label'] }}</span>
    </a>
    <a href="{{ $accountUrl }}" class="mnav-dock-item {{ request()->routeIs('student.*', 'learn.*') ? 'is-active' : '' }}">
        <i class="{{ $accountIcon }}"></i><span>{{ $account ? 'لوحتي' : 'دخول' }}</span>
        @if (($account['unread'] ?? 0) > 0)
            <span class="mnav-account-dot"></span>
        @endif
    </a>
</nav>
