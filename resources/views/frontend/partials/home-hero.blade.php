{{--
    Home hero slider — Motion Academy.
    Keeps the Swiper hooks the page script uses (.hero-swiper, .hero-pagination,
    .hero-prev/.hero-next, .hero-progress-fill, .hero-content). Visuals are pure
    HTML/CSS (styles in assets/css/brand.css, "Home hero" section).
--}}
@php
    $heroCourses = $sliderCourses->take(3)->values();
    $previewTitles = $sliderCourses->take(4)->pluck('title');
@endphp
<section class="hero-section ma-hero position-relative">
    <div class="ma-hero-waves" aria-hidden="true">
        <span class="ma-wave ma-wave-1"></span>
        <span class="ma-wave ma-wave-2"></span>
        <span class="ma-wave ma-wave-3"></span>
        <span class="ma-tri ma-tri-1"></span>
        <span class="ma-tri ma-tri-2"></span>
        <span class="ma-tri ma-tri-3"></span>
    </div>

    <div class="swiper hero-swiper">
        <div class="swiper-wrapper">

            {{-- Slide 1: the academy --}}
            <div class="swiper-slide hero-slide">
                <div class="container hero-slide-inner">
                    <div class="row align-items-center gy-5 min-vh-hero">
                        <div class="col-lg-6 text-center text-lg-start hero-content">
                            <span class="ma-hero-badge"><i class="fas fa-play"></i> We turn ideas into life</span>
                            <h1 class="ma-hero-title">
                                نحوّل <span class="ma-gradient-text">أفكارك</span><br>إلى حركة وحياة
                            </h1>
                            <p class="ma-hero-desc">
                                أكاديمية متخصصة في التحريك الرقمي والتدريب على برامجه، نبني بيئة إبداعية تساعدك على صناعة محتوى بصري احترافي.
                            </p>
                            <div class="hero-actions d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                                <a href="{{ route('courses') }}" class="btn btn-hero-primary"><span>تصفح الدورات</span> <i class="fas fa-arrow-left"></i></a>
                                <a href="{{ route('about') }}" class="btn ma-btn-ghost"><span>من نحن</span> <i class="fas fa-play-circle"></i></a>
                            </div>
                            <div class="ma-hero-stats">
                                <div><strong class="en-text">{{ $stats['courses'] }}</strong><span>دورة</span></div>
                                <div><strong class="en-text">{{ $stats['lessons'] }}</strong><span>درس</span></div>
                                <div><strong class="en-text">{{ $stats['hours'] }}</strong><span>ساعة محتوى</span></div>
                            </div>
                        </div>
                        <div class="col-lg-6 hero-visual d-none d-lg-block">
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
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide 2: motion graphics --}}
            <div class="swiper-slide hero-slide">
                <div class="container hero-slide-inner">
                    <div class="row align-items-center gy-5 min-vh-hero">
                        <div class="col-lg-6 text-center text-lg-start hero-content">
                            <span class="ma-hero-badge"><i class="fas fa-bezier-curve"></i> الموشن جرافيك</span>
                            <h2 class="ma-hero-title">
                                كل إطار <span class="ma-gradient-text">يحكي قصة</span>
                            </h2>
                            <p class="ma-hero-desc">
                                تعلّم الإطارات المفتاحية ومنحنيات السرعة والتوقيت، وحوّل التصاميم الثابتة إلى حركة سلسة تشد المشاهد.
                            </p>
                            <div class="hero-actions d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                                <a href="{{ route('categories') }}" class="btn btn-hero-primary"><span>استكشف المجالات</span> <i class="fas fa-arrow-left"></i></a>
                                <a href="{{ route('courses') }}" class="btn ma-btn-ghost"><span>كل الدورات</span> <i class="fas fa-layer-group"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-6 hero-visual d-none d-lg-block">
                            <div class="ma-curve" aria-hidden="true">
                                <svg viewBox="0 0 520 320" class="ma-curve-svg">
                                    <defs>
                                        <linearGradient id="maCurveGrad" x1="0" x2="1">
                                            <stop offset="0" stop-color="#02ad8f"/>
                                            <stop offset="0.5" stop-color="#45bec4"/>
                                            <stop offset="1" stop-color="#1a83c1"/>
                                        </linearGradient>
                                    </defs>
                                    <path class="ma-curve-guide" d="M40 250 C 150 250, 170 70, 260 70 S 380 250, 480 90"/>
                                    <path class="ma-curve-path" d="M40 250 C 150 250, 170 70, 260 70 S 380 250, 480 90"/>
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
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide 3: real courses --}}
            <div class="swiper-slide hero-slide">
                <div class="container hero-slide-inner">
                    <div class="row align-items-center gy-5 min-vh-hero">
                        <div class="col-lg-6 text-center text-lg-start hero-content">
                            <span class="ma-hero-badge"><i class="fas fa-photo-video"></i> صناعة المحتوى البصري</span>
                            <h2 class="ma-hero-title">
                                دورات عملية <span class="ma-gradient-text">خطوة بخطوة</span>
                            </h2>
                            <p class="ma-hero-desc">
                                منهج مقسّم إلى دروس قصيرة وتطبيقات بعد كل قسم، لتبني ملف أعمالك وأنت تتعلّم.
                            </p>
                            <div class="hero-actions d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                                <a href="{{ route('courses') }}" class="btn btn-hero-primary"><span>شاهد الدورات</span> <i class="fas fa-arrow-left"></i></a>
                                <a href="{{ route('blog.index') }}" class="btn ma-btn-ghost"><span>المدونة</span> <i class="fas fa-pen-nib"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-6 hero-visual d-none d-lg-block">
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
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide 4: start with free previews --}}
            <div class="swiper-slide hero-slide">
                <div class="container hero-slide-inner">
                    <div class="row align-items-center gy-5 min-vh-hero">
                        <div class="col-lg-6 text-center text-lg-start hero-content">
                            <span class="ma-hero-badge"><i class="fas fa-unlock"></i> ابدأ الآن</span>
                            <h2 class="ma-hero-title">
                                جرّب قبل أن <span class="ma-gradient-text">تبدأ</span>
                            </h2>
                            <p class="ma-hero-desc">
                                الدرس الأول من كل دورة متاح للمعاينة مجاناً — شاهده وقرّر بنفسك أين تبدأ رحلتك.
                            </p>
                            <div class="hero-actions d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                                <a href="{{ route('courses') }}" class="btn btn-hero-primary"><span>ابدأ بدرس مجاني</span> <i class="fas fa-arrow-left"></i></a>
                                @guest
                                    <a href="{{ route('register') }}" class="btn ma-btn-ghost"><span>أنشئ حسابك</span> <i class="fas fa-user-plus"></i></a>
                                @endguest
                            </div>
                        </div>
                        <div class="col-lg-6 hero-visual d-none d-lg-block">
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
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="hero-swiper-nav">
            <button class="hero-nav-btn hero-prev" aria-label="الشريحة السابقة"><i class="fas fa-chevron-right"></i></button>
            <div class="swiper-pagination hero-pagination"></div>
            <button class="hero-nav-btn hero-next" aria-label="الشريحة التالية"><i class="fas fa-chevron-left"></i></button>
        </div>

        <div class="hero-autoplay-progress"><span class="hero-progress-fill"></span></div>
    </div>
</section>
