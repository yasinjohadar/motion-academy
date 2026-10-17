@extends('frontend.layouts.master')

{{--
    "Who we are", told the way our students work: as a motion project.
      Hero      a camera frame with a keyframed path ("We turn ideas into life")
      Numbers   a render-stats panel (real counts)
      Story     a four-scene storyboard on a timeline; the playhead follows the scroll
      Values    a layers panel
      Fields    the real categories, as tracks
      Crew      the instructors, as closing credits
    Styles: brand-story.css (ws-*). Behaviour: js/story.js.
--}}

@section('body_class', 'who-we-are-page ws-page')

@php
    $scenes = [
        ['code' => 'SC 01', 'icon' => 'ri-lightbulb-flash-line', 'title' => 'الفكرة', 'tone' => '#f59e0b',
            'text' => 'كل عمل يبدأ بفكرة صغيرة. نعلّمك كيف تلتقطها، وتكتبها، وتحوّلها إلى قصة تستحق أن تُروى.'],
        ['code' => 'SC 02', 'icon' => 'ri-pen-nib-line', 'title' => 'الرسم', 'tone' => '#33a4da',
            'text' => 'من الورقة إلى الشاشة: التكوين، والألوان، والشخصيات — الأساس الذي تقف عليه كل حركة.'],
        ['code' => 'SC 03', 'icon' => 'ri-film-line', 'title' => 'التحريك', 'tone' => '#45bec4',
            'text' => 'الإطارات المفتاحية، ومنحنيات السرعة، والتوقيت. هنا تتعلّم كيف يتنفّس التصميم.'],
        ['code' => 'SC 04', 'icon' => 'ri-sparkling-2-line', 'title' => 'الحياة', 'tone' => '#02ad8f',
            'text' => 'عملك يخرج إلى العالم: مشروع حقيقي في ملفّ أعمالك، ومهارة تفتح لك أبواب العمل.'],
    ];

    $layers = [
        ['color' => '#02ad8f', 'icon' => 'ri-hand-heart-line', 'name' => 'نتعلّم بالتطبيق', 'text' => 'كل درس ينتهي بشيء تصنعه بيدك، لا بمعلومة تحفظها.', 'keys' => [12, 48, 80]],
        ['color' => '#33a4da', 'icon' => 'ri-translate-2', 'name' => 'بالعربية أولاً', 'text' => 'محتوى عربي أصيل بلغة واضحة، لا ترجمة حرفية.', 'keys' => [22, 60]],
        ['color' => '#45bec4', 'icon' => 'ri-footprint-line', 'name' => 'خطوة بخطوة', 'text' => 'منهج متدرّج من الصفر، مع متابعة لتقدّمك في كل دورة.', 'keys' => [8, 34, 58, 86]],
        ['color' => '#1a83c1', 'icon' => 'ri-team-line', 'name' => 'مجتمع يكبر معك', 'text' => 'طلاب ومدرّبون يشاركونك الطريق ويحتفلون بإنجازك.', 'keys' => [40, 72]],
    ];

    $statItems = [
        ['value' => $stats['courses'], 'label' => 'دورة منشورة', 'icon' => 'ri-movie-2-line', 'code' => 'COMP'],
        ['value' => $stats['lessons'], 'label' => 'درس عملي', 'icon' => 'ri-film-line', 'code' => 'FRAMES'],
        ['value' => $stats['hours'], 'label' => 'ساعة محتوى', 'icon' => 'ri-time-line', 'code' => 'DURATION'],
        ['value' => $stats['students'], 'label' => 'طالب معنا', 'icon' => 'ri-user-smile-line', 'code' => 'VIEWERS'],
    ];

    $ctaUrl = auth()->check() ? route('student.dashboard') : route('register');
@endphp

@section('content')
    {{-- ============ Hero: the camera frame ============ --}}
    <section class="ws-hero">
        <div class="container">
            <div class="ws-hero-grid">
                <div class="ws-hero-copy" data-ws-reveal>
                    <span class="ws-kicker"><span class="ws-rec"></span> من نحن</span>
                    <h1 class="ws-hero-title">نحوّل الأفكار إلى <span class="ws-grad">حركة</span></h1>
                    <p class="ws-hero-text">
                        أكاديمية عربية لتعليم التحريك وصناعة المحتوى البصري. نأخذ بيدك من أول فكرة
                        إلى مشهد ينبض بالحياة — بدروس عملية، خطوة بخطوة، وبلغتك.
                    </p>
                    <p class="ws-hero-tagline en-text">We turn ideas into life</p>
                    <div class="ws-hero-actions">
                        <a href="{{ route('courses') }}" class="ws-btn"><i class="fas fa-play"></i> تصفّح الدورات</a>
                        <a href="#ws-story" class="ws-btn ws-btn-ghost"><i class="ri-film-line"></i> شاهد قصتنا</a>
                    </div>
                </div>

                <div class="ws-frame" data-ws-reveal aria-hidden="true">
                    <span class="ws-frame-corner is-tl"></span><span class="ws-frame-corner is-tr"></span>
                    <span class="ws-frame-corner is-bl"></span><span class="ws-frame-corner is-br"></span>
                    <span class="ws-frame-label en-text">CAM 01 · 1920×1080 · 24fps</span>
                    <span class="ws-frame-safe"></span>

                    <svg class="ws-path" viewBox="0 0 400 260" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="wsPathGrad" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#02ad8f" />
                                <stop offset="100%" stop-color="#33a4da" />
                            </linearGradient>
                            <radialGradient id="wsBallGrad" cx="35%" cy="35%" r="70%">
                                <stop offset="0%" stop-color="#ffffff" />
                                <stop offset="45%" stop-color="#45bec4" />
                                <stop offset="100%" stop-color="#1a83c1" />
                            </radialGradient>
                        </defs>
                        <path id="wsMotion" class="ws-path-line" d="M30 200 C 90 40, 150 40, 200 130 S 320 230, 370 60" />
                        <g class="ws-keys">
                            <rect x="25" y="195" width="10" height="10" transform="rotate(45 30 200)" />
                            <rect x="195" y="125" width="10" height="10" transform="rotate(45 200 130)" />
                            <rect x="365" y="55" width="10" height="10" transform="rotate(45 370 60)" />
                        </g>
                        <g class="ws-handles">
                            <line x1="200" y1="130" x2="150" y2="40" /><circle cx="150" cy="40" r="4" />
                            <line x1="200" y1="130" x2="250" y2="220" /><circle cx="250" cy="220" r="4" />
                        </g>
                        <circle class="ws-ball-shadow" r="14">
                            <animateMotion dur="4.8s" repeatCount="indefinite" keyPoints="0;1;0" keyTimes="0;0.5;1" calcMode="spline" keySplines="0.45 0 0.55 1;0.45 0 0.55 1">
                                <mpath href="#wsMotion" />
                            </animateMotion>
                        </circle>
                        <circle class="ws-ball" r="11" fill="url(#wsBallGrad)">
                            <animateMotion dur="4.8s" repeatCount="indefinite" keyPoints="0;1;0" keyTimes="0;0.5;1" calcMode="spline" keySplines="0.45 0 0.55 1;0.45 0 0.55 1">
                                <mpath href="#wsMotion" />
                            </animateMotion>
                        </circle>
                    </svg>

                    <div class="ws-timeline">
                        <span class="ws-timecode en-text" data-ws-timecode>00:00:00:00</span>
                        <div class="ws-track">
                            <span class="ws-track-key" style="--at: 0%"></span>
                            <span class="ws-track-key" style="--at: 50%"></span>
                            <span class="ws-track-key" style="--at: 100%"></span>
                            <span class="ws-playhead"></span>
                        </div>
                        <span class="ws-fps en-text">24 fps</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============ Numbers: render stats ============ --}}
    <section class="ws-stats-wrap">
        <div class="container">
            <div class="ws-stats" data-ws-reveal>
                @foreach ($statItems as $item)
                    <div class="ws-stat">
                        <span class="ws-stat-code en-text">{{ $item['code'] }}</span>
                        <span class="ws-stat-icon"><i class="{{ $item['icon'] }}"></i></span>
                        <strong class="en-text" data-ws-count="{{ $item['value'] }}">{{ number_format($item['value']) }}</strong>
                        <span class="ws-stat-label">{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============ Story: four scenes on a timeline ============ --}}
    <section class="ws-story" id="ws-story" data-ws-story>
        <div class="container">
            <header class="ws-head" data-ws-reveal>
                <span class="ws-kicker"><i class="ri-film-line"></i> قصتنا في أربعة مشاهد</span>
                <h2 class="ws-title">رحلة كل طالب… هي رحلة كل <span class="ws-grad">رسم متحرّك</span></h2>
                <p class="ws-lead">لم نبنِ منصة دروس فحسب؛ بنينا الطريق نفسه الذي يمرّ به كل عمل بصري، وجعلناه منهجاً تمشيه معنا.</p>
            </header>

            <div class="ws-rail" aria-hidden="true">
                <span class="ws-rail-fill"></span>
                <span class="ws-rail-head"></span>
            </div>

            <ol class="ws-scenes">
                @foreach ($scenes as $index => $scene)
                    <li class="ws-scene" style="--tone: {{ $scene['tone'] }}; --i: {{ $index }}" data-ws-reveal>
                        <span class="ws-scene-key" aria-hidden="true"></span>
                        <div class="ws-scene-frame">
                            <span class="ws-sprockets" aria-hidden="true"></span>
                            <span class="ws-scene-code en-text">{{ $scene['code'] }}</span>
                            <span class="ws-scene-art"><i class="{{ $scene['icon'] }}"></i></span>
                            <span class="ws-sprockets is-bottom" aria-hidden="true"></span>
                        </div>
                        <h3 class="ws-scene-title">{{ $scene['title'] }}</h3>
                        <p class="ws-scene-text">{{ $scene['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ============ Values: the layers panel ============ --}}
    <section class="ws-values">
        <div class="container">
            <div class="ws-values-grid">
                <header class="ws-head is-start" data-ws-reveal>
                    <span class="ws-kicker"><i class="ri-stack-line"></i> ما الذي يحرّكنا</span>
                    <h2 class="ws-title">طبقات تصنع <span class="ws-grad">التجربة</span></h2>
                    <p class="ws-lead">كأي مشهد متقن، تجربتنا طبقات فوق بعضها. كل طبقة لها دور، وإن غابت واحدة اختلّ المشهد كله.</p>
                </header>

                <div class="ws-layers" data-ws-reveal>
                    <div class="ws-layers-bar">
                        <span class="ws-dots"><i></i><i></i><i></i></span>
                        <span class="en-text">Layers — motion-academy.comp</span>
                    </div>
                    @foreach ($layers as $index => $layer)
                        <div class="ws-layer" style="--c: {{ $layer['color'] }}">
                            <span class="ws-layer-tag"></span>
                            <span class="ws-layer-eye"><i class="ri-eye-line"></i></span>
                            <span class="ws-layer-num en-text">{{ $index + 1 }}</span>
                            <span class="ws-layer-icon"><i class="{{ $layer['icon'] }}"></i></span>
                            <div class="ws-layer-body">
                                <strong>{{ $layer['name'] }}</strong>
                                <p>{{ $layer['text'] }}</p>
                            </div>
                            <div class="ws-layer-track" aria-hidden="true">
                                <span class="ws-layer-bar"></span>
                                @foreach ($layer['keys'] as $key)
                                    <span class="ws-layer-key" style="--at: {{ $key }}%"></span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============ Fields we teach ============ --}}
    @if ($categories->isNotEmpty())
        <section class="ws-tracks">
            <div class="container">
                <header class="ws-head" data-ws-reveal>
                    <span class="ws-kicker"><i class="ri-route-line"></i> مساراتنا</span>
                    <h2 class="ws-title">ماذا <span class="ws-grad">نعلّم</span>؟</h2>
                </header>
                <div class="ws-track-grid">
                    @foreach ($categories as $category)
                        <a href="{{ route('courses', ['category' => $category->slug]) }}" class="ws-field" style="--c: {{ $category->color ?: '#02ad8f' }}" data-ws-reveal>
                            <span class="ws-field-icon"><i class="{{ $category->publicIcon() }}"></i></span>
                            <span class="ws-field-name">{{ $category->name }}</span>
                            <span class="ws-field-count en-text">{{ $category->courses_count }} <small>دورة</small></span>
                            <i class="fas fa-arrow-left ws-field-go"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Crew: closing credits ============ --}}
    @if ($instructors->isNotEmpty())
        <section class="ws-credits">
            <div class="container">
                <header class="ws-head" data-ws-reveal>
                    <span class="ws-kicker is-light"><i class="ri-clapperboard-line"></i> فريق العمل</span>
                    <h2 class="ws-title is-light">الأسماء خلف <span class="ws-grad">المشاهد</span></h2>
                </header>
                <ul class="ws-crew">
                    @foreach ($instructors as $person)
                        <li data-ws-reveal>
                            <span class="ws-crew-avatar">{{ $person['initial'] }}</span>
                            <span class="ws-crew-role">مدرّب</span>
                            <strong class="ws-crew-name">{{ $person['name'] }}</strong>
                            <span class="ws-crew-meta"><span class="en-text">{{ $person['courses'] }}</span> {{ $person['courses'] === 1 ? 'دورة' : 'دورات' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- ============ CTA ============ --}}
    <section class="ws-cta-wrap">
        <div class="container">
            <div class="ws-cta" data-ws-reveal>
                <span class="ws-cta-rec"><span class="ws-rec"></span> REC</span>
                <h2>مشهدك الأول يبدأ <span class="ws-grad-light">الآن</span></h2>
                <p>اختر دورة، ابدأ بدرس مجاني، وتابع تقدّمك من لوحتك — ونحن معك في كل إطار.</p>
                <div class="ws-hero-actions is-center">
                    <a href="{{ $ctaUrl }}" class="ws-btn ws-btn-light">
                        <i class="fas fa-rocket"></i> {{ auth()->check() ? 'لوحتي' : 'أنشئ حسابك مجاناً' }}
                    </a>
                    <a href="{{ route('courses') }}" class="ws-btn ws-btn-outline"><i class="fas fa-play"></i> تصفّح الدورات</a>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ $fa }}/js/story.js"></script>
@endpush
