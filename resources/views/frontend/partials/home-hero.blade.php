{{--
    Home hero slider — Motion Academy.
    Slides and slider behaviour come from the admin panel (HeroSlide rows and
    HeroSliderSettings). Keeps the Swiper hooks the page script uses
    (.hero-swiper, .hero-pagination, .hero-prev/.hero-next, .hero-progress-fill,
    .hero-content). Visuals are pure HTML/CSS (assets/css/brand-hero.css).
--}}
@php
    use App\Enums\HeroSlideVisual;

    $heroCourses = $sliderCourses->take(3)->values();
    $previewTitles = $sliderCourses->take(4)->pluck('title');
    $hasManySlides = $heroSlides->count() > 1;
@endphp
@if ($heroSlides->isNotEmpty())
<section class="hero-section ma-hero position-relative">
    <div class="ma-hero-waves" aria-hidden="true">
        <span class="ma-wave ma-wave-1"></span>
        <span class="ma-wave ma-wave-2"></span>
        <span class="ma-wave ma-wave-3"></span>
        <span class="ma-tri ma-tri-1"></span>
        <span class="ma-tri ma-tri-2"></span>
        <span class="ma-tri ma-tri-3"></span>
    </div>

    <div class="swiper hero-swiper" data-hero-settings='@json($heroSettings)'>
        <div class="swiper-wrapper">

            @foreach ($heroSlides as $slide)
                @php $hasVisual = $slide->visual !== HeroSlideVisual::None && ($slide->visual !== HeroSlideVisual::Image || $slide->imageUrl()); @endphp
                <div class="swiper-slide hero-slide" data-bullet-label="{{ $slide->bullet_label ?: $loop->iteration }}">
                    <div class="container hero-slide-inner">
                        <div class="row align-items-center gy-5 min-vh-hero {{ $hasVisual ? '' : 'justify-content-center' }}">
                            <div class="{{ $hasVisual ? 'col-lg-6 text-center text-lg-start' : 'col-lg-9 text-center ma-hero-solo' }} hero-content">
                                @if ($slide->badge_text)
                                    <span class="ma-hero-badge">@if ($slide->badge_icon)<i class="{{ $slide->badge_icon }}"></i> @endif{{ $slide->badge_text }}</span>
                                @endif
                                <{{ $loop->first ? 'h1' : 'h2' }} class="ma-hero-title">{{ $slide->titleHtml() }}</{{ $loop->first ? 'h1' : 'h2' }}>
                                @if ($slide->description)
                                    <p class="ma-hero-desc">{{ $slide->description }}</p>
                                @endif
                                @if ($slide->primary_button_url || $slide->secondary_button_url)
                                    <div class="hero-actions d-flex flex-wrap gap-3 justify-content-center {{ $hasVisual ? 'justify-content-lg-start' : '' }}">
                                        @if ($slide->primary_button_url)
                                            <a href="{{ $slide->primary_button_url }}" class="btn btn-hero-primary"><span>{{ $slide->primary_button_label }}</span>@if ($slide->primary_button_icon) <i class="{{ $slide->primary_button_icon }}"></i>@endif</a>
                                        @endif
                                        @if ($slide->secondary_button_url)
                                            <a href="{{ $slide->secondary_button_url }}" class="btn ma-btn-ghost"><span>{{ $slide->secondary_button_label }}</span>@if ($slide->secondary_button_icon) <i class="{{ $slide->secondary_button_icon }}"></i>@endif</a>
                                        @endif
                                    </div>
                                @endif
                                @if ($slide->show_stats)
                                    <div class="ma-hero-stats">
                                        <div><strong class="en-text">{{ $stats['courses'] }}</strong><span>دورة</span></div>
                                        <div><strong class="en-text">{{ $stats['lessons'] }}</strong><span>درس</span></div>
                                        <div><strong class="en-text">{{ $stats['hours'] }}</strong><span>ساعة محتوى</span></div>
                                    </div>
                                @endif
                            </div>

                            @if ($hasVisual)
                                <div class="col-lg-6 hero-visual d-none d-lg-block">
                                    @switch($slide->visual)
                                        @case(HeroSlideVisual::Editor)
                                            <div class="ma-editor" aria-hidden="true">
                                                <div class="ma-editor-top"><span></span><span></span><span></span><em>Motion Academy</em></div>
                                                <div class="ma-editor-body">
                                                    <div class="ma-editor-tools"><i class="fas fa-mouse-pointer"></i><i class="fas fa-pen-nib"></i><i class="fas fa-shapes"></i><i class="fas fa-font"></i></div>
                                                    <div class="ma-editor-stage">
                                                        <span class="ma-stage-shape"></span>
                                                        <button type="button" class="ma-stage-play" tabindex="-1"><i class="fas fa-play"></i></button>
                                                    </div>
                                                </div>
                                                <div class="ma-timeline">
                                                    <div class="ma-track"><i style="--s: 6%; --w: 38%;"></i></div>
                                                    <div class="ma-track ma-track-blue"><i style="--s: 28%; --w: 34%;"></i></div>
                                                    <div class="ma-track"><i style="--s: 52%; --w: 30%;"></i></div>
                                                    <span class="ma-playhead"></span>
                                                </div>
                                            </div>
                                            <img src="{{ $fa }}/images/logo-mark.png" alt="" class="ma-float-logo" aria-hidden="true">
                                            @break

                                        @case(HeroSlideVisual::Curve)
                                            <div class="ma-curve" aria-hidden="true">
                                                <svg viewBox="0 0 520 320" class="ma-curve-svg">
                                                    <defs>
                                                        <linearGradient id="maCurveGrad{{ $slide->id }}" x1="0" x2="1">
                                                            <stop offset="0" stop-color="#02ad8f"/>
                                                            <stop offset="0.5" stop-color="#45bec4"/>
                                                            <stop offset="1" stop-color="#1a83c1"/>
                                                        </linearGradient>
                                                    </defs>
                                                    <path class="ma-curve-guide" d="M40 250 C 150 250, 170 70, 260 70 S 380 250, 480 90"/>
                                                    <path class="ma-curve-path" style="stroke: url(#maCurveGrad{{ $slide->id }});" d="M40 250 C 150 250, 170 70, 260 70 S 380 250, 480 90"/>
                                                    <line x1="40" y1="250" x2="150" y2="250" class="ma-handle"/>
                                                    <line x1="260" y1="70" x2="170" y2="70" class="ma-handle"/>
                                                    <rect x="32" y="242" width="16" height="16" rx="3" class="ma-key" transform="rotate(45 40 250)"/>
                                                    <rect x="252" y="62" width="16" height="16" rx="3" class="ma-key" transform="rotate(45 260 70)"/>
                                                    <rect x="472" y="82" width="16" height="16" rx="3" class="ma-key" transform="rotate(45 480 90)"/>
                                                    <circle cx="150" cy="250" r="6" class="ma-handle-dot"/>
                                                    <circle cx="170" cy="70" r="6" class="ma-handle-dot"/>
                                                </svg>
                                                <span class="ma-curve-ball"></span>
                                                <div class="ma-curve-label ma-curve-label-1">Ease In</div>
                                                <div class="ma-curve-label ma-curve-label-2">Ease Out</div>
                                            </div>
                                            @break

                                        @case(HeroSlideVisual::Courses)
                                            <div class="ma-stack">
                                                @forelse ($heroCourses as $index => $course)
                                                    <a href="{{ route('courses.show', $course->slug) }}" class="ma-stack-card ma-stack-card-{{ $index + 1 }}" style="--card-color: {{ $course->category?->color ?? '#02ad8f' }};">
                                                        <span class="ma-stack-thumb">
                                                            @if ($thumb = $course->thumbnailUrl())
                                                                <img src="{{ $thumb }}" alt="" loading="lazy">
                                                            @else
                                                                <i class="{{ $course->category?->publicIcon() ?? 'fas fa-play' }}"></i>
                                                            @endif
                                                        </span>
                                                        <span class="ma-stack-body">
                                                            <small>{{ $course->category?->name }}</small>
                                                            <strong>{{ $course->title }}</strong>
                                                            <span class="ma-stack-meta">
                                                                <span><i class="fas fa-play-circle"></i> {{ $course->lessons_count }} درس</span>
                                                                <span class="ma-stack-price">{{ $course->formattedPrice() }}</span>
                                                            </span>
                                                        </span>
                                                    </a>
                                                @empty
                                                    <div class="ma-stack-card ma-stack-card-1">
                                                        <span class="ma-stack-thumb"><i class="fas fa-play"></i></span>
                                                        <span class="ma-stack-body"><strong>الدورات قادمة قريباً</strong></span>
                                                    </div>
                                                @endforelse
                                            </div>
                                            @break

                                        @case(HeroSlideVisual::Preview)
                                            <div class="ma-phone" aria-hidden="true">
                                                <div class="ma-phone-screen">
                                                    <div class="ma-phone-video"><span class="ma-pulse"><i class="fas fa-play"></i></span></div>
                                                    <ul class="ma-phone-list">
                                                        @foreach ($previewTitles->isNotEmpty() ? $previewTitles : collect(['مقدمة الدورة', 'الأدوات الأساسية', 'أول مشروع', 'ملفات التمرين']) as $i => $title)
                                                            <li class="{{ $i === 0 ? 'is-free' : '' }}">
                                                                <i class="{{ $i === 0 ? 'fas fa-unlock' : 'fas fa-lock' }}"></i>
                                                                <span>{{ \Illuminate\Support\Str::limit($title, 30) }}</span>
                                                                @if ($i === 0)<em>مجاني</em>@endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            </div>
                                            @break

                                        @case(HeroSlideVisual::Image)
                                            <figure class="ma-hero-image">
                                                <img src="{{ $slide->imageUrl() }}" alt="{{ $slide->image_alt ?? '' }}" @if (! $loop->first) loading="lazy" @endif>
                                            </figure>
                                            @break
                                    @endswitch
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

        </div>

        @if ($hasManySlides && ($heroSettings['show_navigation'] || $heroSettings['show_pagination']))
            <div class="hero-swiper-nav">
                @if ($heroSettings['show_navigation'])
                    <button class="hero-nav-btn hero-prev" aria-label="الشريحة السابقة"><i class="fas fa-chevron-right"></i></button>
                @endif
                @if ($heroSettings['show_pagination'])
                    <div class="swiper-pagination hero-pagination"></div>
                @endif
                @if ($heroSettings['show_navigation'])
                    <button class="hero-nav-btn hero-next" aria-label="الشريحة التالية"><i class="fas fa-chevron-left"></i></button>
                @endif
            </div>
        @endif

        @if ($hasManySlides && $heroSettings['autoplay'] && $heroSettings['show_progress'])
            <div class="hero-autoplay-progress"><span class="hero-progress-fill"></span></div>
        @endif
    </div>
</section>
@endif
