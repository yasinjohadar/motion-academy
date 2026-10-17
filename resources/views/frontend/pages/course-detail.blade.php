@extends('frontend.layouts.master')

@section('body_class', 'course-detail-page cd-page')

@php
    /** @var \App\Models\Course $course */
    $categoryColor = $course->category?->color ?? '#02ad8f';
    $icon = $course->category?->publicIcon() ?? 'fas fa-play';
    $thumbnailUrl = $course->thumbnailUrl();
    $sections = $course->sections;
    $lessonIcons = ['video' => 'fas fa-play', 'text' => 'fas fa-file-alt', 'file' => 'fas fa-paperclip'];
    $intro = trim(html_entity_decode(strip_tags((string) $course->short_description)));
    // The subtitle often repeats the short description; show it once.
    $subtitle = $course->subtitle && trim($course->subtitle) !== $intro ? $course->subtitle : null;
    $firstPreview = $previews[0] ?? null;
    $previewIds = collect($previews)->pluck('id')->all();
@endphp

@section('content')
    {{-- ================= Hero ================= --}}
    <section class="cd-hero" style="--cat-color: {{ $categoryColor }};">
        <div class="cd-hero-waves" aria-hidden="true"><span></span><span></span><i></i><i></i></div>
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="cd-breadcrumb">
                    <li><a href="{{ route('home') }}">الرئيسية</a></li>
                    <li><a href="{{ route('courses') }}">الكورسات</a></li>
                    @if ($course->category)
                        <li><a href="{{ route('courses', ['categories' => [$course->category->slug]]) }}">{{ $course->category->name }}</a></li>
                    @endif
                    <li aria-current="page">{{ $course->title }}</li>
                </ol>
            </nav>

            <div class="row g-4 g-xl-5 align-items-center">
                <div class="col-lg-6 order-2 order-lg-1">
                    <div class="cd-badges">
                        @if ($course->category)
                            <span class="cd-badge cd-badge-cat"><i class="{{ $icon }}"></i> {{ $course->category->name }}</span>
                        @endif
                        <span class="cd-badge"><i class="fas fa-signal"></i> {{ $course->level->label() }}</span>
                        @if ($badge = $course->publicBadge())
                            <span class="cd-badge cd-badge-hot">{{ $badge }}</span>
                        @endif
                    </div>

                    <h1 class="cd-title">{{ $course->title }}</h1>
                    @if ($subtitle)
                        <p class="cd-subtitle">{{ $subtitle }}</p>
                    @endif
                    @if ($intro !== '')
                        <p class="cd-intro">{{ $intro }}</p>
                    @endif

                    <ul class="cd-meta">
                        @if ($course->instructor)
                            <li><span class="cd-avatar">{{ mb_substr($course->instructor->name, 0, 1) }}</span><span><small>المدرّس</small>{{ $course->instructor->name }}</span></li>
                        @endif
                        <li><i class="fas fa-play-circle"></i><span><small>الدروس</small><b class="en-text">{{ $course->lessons_count }}</b> درس</span></li>
                        <li><i class="far fa-clock"></i><span><small>المدة</small>{{ $course->formattedDuration() }}</span></li>
                        <li><i class="fas fa-globe"></i><span><small>اللغة</small>{{ $course->languageLabel() }}</span></li>
                    </ul>

                    <div class="cd-hero-buy">
                        <div class="cd-price">
                            <strong class="en-text">{{ $course->formattedPrice() }}</strong>
                            @if ($original = $course->formattedOriginalPrice())
                                <del class="en-text">{{ $original }}</del>
                            @endif
                            @if ($course->isOnSale())
                                <span class="cd-discount">-{{ $course->discountPercent() }}%</span>
                            @endif
                        </div>
                        @if ($firstPreview)
                            <a href="#course-preview" class="cd-btn-preview js-play-preview" data-preview="{{ $firstPreview['id'] }}">
                                <span class="cd-btn-preview-icon"><i class="fas fa-play"></i></span>
                                شاهد معاينة مجانية
                            </a>
                        @endif
                    </div>
                    @if ($course->isOnSale() && $course->sale_ends_at)
                        <p class="cd-sale-ends"><i class="fas fa-hourglass-half"></i> ينتهي الخصم {{ $course->sale_ends_at->diffForHumans() }}</p>
                    @endif
                </div>

                {{-- Preview player --}}
                <div class="col-lg-6 order-1 order-lg-2">
                    <div class="cd-player-card" id="course-preview">
                        <div class="cd-player" data-player>
                            <div class="cd-player-poster" data-poster>
                                @if ($thumbnailUrl)
                                    <img src="{{ $thumbnailUrl }}" alt="{{ $course->title }}">
                                @else
                                    <div class="cd-player-art" aria-hidden="true">
                                        <i class="{{ $icon }}"></i>
                                        <span class="cd-art-track"><b></b><b></b><b></b></span>
                                    </div>
                                @endif
                                @if ($firstPreview)
                                    <button type="button" class="cd-play-btn js-play-preview" data-preview="{{ $firstPreview['id'] }}" aria-label="تشغيل المعاينة">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <span class="cd-player-caption"><i class="fas fa-unlock"></i> {{ $firstPreview['title'] }}</span>
                                @else
                                    <span class="cd-player-caption"><i class="fas fa-lock"></i> لا توجد معاينة متاحة لهذه الدورة بعد</span>
                                @endif
                            </div>
                            <div class="cd-player-frame" data-frame hidden></div>
                        </div>

                        @if (count($previews) > 0)
                            <div class="cd-playlist">
                                <div class="cd-playlist-head">
                                    <span><i class="fas fa-unlock"></i> معاينات مجانية</span>
                                    <em class="en-text">{{ count($previews) }}</em>
                                </div>
                                <ol>
                                    @foreach ($previews as $index => $preview)
                                        <li>
                                            <button type="button" class="cd-playlist-item js-play-preview" data-preview="{{ $preview['id'] }}">
                                                <span class="cd-playlist-num en-text">{{ $index + 1 }}</span>
                                                <span class="cd-playlist-title">{{ $preview['title'] }}</span>
                                                <span class="cd-playlist-meta">{{ $preview['duration'] ?? $preview['label'] }}</span>
                                                <i class="fas fa-play cd-playlist-play"></i>
                                            </button>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= Body ================= --}}
    <main class="container cd-main">
        <div class="row g-4 g-xl-5">
            <div class="col-lg-8">
                <nav class="cd-tabs" id="courseTab" role="tablist">
                    <button class="cd-tab active" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab"><i class="fas fa-layer-group"></i> محتوى الدورة</button>
                    <button class="cd-tab" data-bs-toggle="tab" data-bs-target="#requirements" type="button" role="tab"><i class="fas fa-clipboard-check"></i> المتطلبات</button>
                    @if ($course->instructor)
                        <button class="cd-tab" data-bs-toggle="tab" data-bs-target="#instructor" type="button" role="tab"><i class="fas fa-user-tie"></i> المدرّس</button>
                    @endif
                </nav>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="content" role="tabpanel">
                        @if (! empty($course->learning_outcomes))
                            <section class="cd-card">
                                <h2 class="cd-card-title"><i class="fas fa-bullseye"></i> ماذا ستتعلم</h2>
                                <ul class="cd-outcomes">
                                    @foreach ($course->learning_outcomes as $item)
                                        <li><i class="fas fa-check"></i><span>{{ $item }}</span></li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif

                        @if ($course->description)
                            <section class="cd-card">
                                <h2 class="cd-card-title"><i class="fas fa-align-right"></i> عن الدورة</h2>
                                {{-- Admin-authored HTML from the rich-text editor. --}}
                                <div class="cd-description">{!! $course->description !!}</div>
                            </section>
                        @endif

                        @if ($sections->isNotEmpty())
                            <section class="cd-card">
                                <div class="cd-card-head">
                                    <h2 class="cd-card-title"><i class="fas fa-list-ol"></i> منهج الدورة</h2>
                                    <span class="cd-card-sub"><b class="en-text">{{ $sections->count() }}</b> أقسام · <b class="en-text">{{ $course->lessons_count }}</b> درس · {{ $course->formattedDuration() }}</span>
                                </div>

                                <div class="cd-curriculum accordion" id="courseAccordion">
                                    @foreach ($sections as $index => $section)
                                        @php
                                            $collapseId = 'module-' . $section->id;
                                        @endphp
                                        <div class="cd-module">
                                            <button class="cd-module-head {{ $index === 0 ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}">
                                                <span class="cd-module-num en-text">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                                <span class="cd-module-title">{{ $section->title }}</span>
                                                <span class="cd-module-meta">{{ $section->lessons->count() }} دروس</span>
                                                <i class="fas fa-chevron-down cd-module-chevron"></i>
                                            </button>
                                            <div id="{{ $collapseId }}" class="collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#courseAccordion">
                                                @if ($section->description)
                                                    <div class="cd-module-desc">{!! $section->description !!}</div>
                                                @endif
                                                <ul class="cd-lessons">
                                                    @foreach ($section->lessons as $lesson)
                                                        @php
                                                            $previewId = 'lesson-' . $lesson->id;
                                                            $playable = in_array($previewId, $previewIds, true);
                                                        @endphp
                                                        <li class="cd-lesson {{ $lesson->is_preview ? 'is-preview' : '' }}">
                                                            <span class="cd-lesson-icon"><i class="{{ $lesson->is_preview ? 'fas fa-unlock' : ($lessonIcons[$lesson->type->value] ?? 'fas fa-play') }}"></i></span>
                                                            @if ($canLearn)
                                                                <a href="{{ route('learn.lesson', [$course->slug, $lesson->id]) }}" class="cd-lesson-title cd-lesson-open">{{ $lesson->title }}</a>
                                                            @else
                                                                <span class="cd-lesson-title">{{ $lesson->title }}</span>
                                                            @endif
                                                            @if ($canLearn)
                                                                <a href="{{ route('learn.lesson', [$course->slug, $lesson->id]) }}" class="cd-lesson-watch"><i class="fas fa-play"></i> ادخل</a>
                                                            @elseif ($playable)
                                                                <button type="button" class="cd-lesson-watch js-play-preview" data-preview="{{ $previewId }}"><i class="fas fa-play"></i> شاهد مجاناً</button>
                                                            @elseif ($lesson->is_preview)
                                                                <span class="cd-lesson-free">مجاني</span>
                                                            @else
                                                                <i class="fas fa-lock cd-lesson-lock" aria-label="مقفل"></i>
                                                            @endif
                                                            <span class="cd-lesson-duration en-text">{{ $lesson->formattedDuration() ?? $lesson->type->label() }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>

                    <div class="tab-pane fade" id="requirements" role="tabpanel">
                        <section class="cd-card">
                            <h2 class="cd-card-title"><i class="fas fa-clipboard-check"></i> متطلبات الدورة</h2>
                            <ul class="cd-outcomes">
                                @forelse ($course->requirements ?? [] as $requirement)
                                    <li><i class="fas fa-check"></i><span>{{ $requirement }}</span></li>
                                @empty
                                    <li><i class="fas fa-smile"></i><span>لا توجد متطلبات — الدورة تبدأ معك من الصفر.</span></li>
                                @endforelse
                            </ul>
                        </section>
                        @if (! empty($course->target_audience))
                            <section class="cd-card">
                                <h2 class="cd-card-title"><i class="fas fa-users"></i> لمن هذه الدورة؟</h2>
                                <ul class="cd-outcomes">
                                    @foreach ($course->target_audience as $audience)
                                        <li><i class="fas fa-user-check"></i><span>{{ $audience }}</span></li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </div>

                    @if ($course->instructor)
                        <div class="tab-pane fade" id="instructor" role="tabpanel">
                            <section class="cd-card cd-instructor">
                                <span class="cd-instructor-avatar">{{ mb_substr($course->instructor->name, 0, 1) }}</span>
                                <div>
                                    <h2 class="cd-card-title mb-1">{{ $course->instructor->name }}</h2>
                                    <p class="cd-card-sub mb-0">مدرّس الدورة{{ $course->category ? ' · ' . $course->category->name : '' }}</p>
                                </div>
                            </section>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Sticky sidebar --}}
            <div class="col-lg-4">
                <aside class="cd-side">
                    <div class="cd-side-price">
                        <strong class="en-text">{{ $course->formattedPrice() }}</strong>
                        @if ($original = $course->formattedOriginalPrice())
                            <del class="en-text">{{ $original }}</del>
                            <span class="cd-discount">-{{ $course->discountPercent() }}%</span>
                        @endif
                    </div>
                    @include('frontend.partials.enrollment-box', ['course' => $course, 'enrollment' => $enrollment, 'isFull' => $isFull])
                    @if ($firstPreview)
                        <a href="#course-preview" class="cd-btn-outline js-play-preview" data-preview="{{ $firstPreview['id'] }}">
                            <i class="fas fa-play-circle"></i> شاهد درساً مجانياً
                        </a>
                    @endif

                    <h3 class="cd-side-title">هذه الدورة تتضمن</h3>
                    <ul class="cd-includes">
                        <li><i class="fas fa-video"></i> {{ $course->formattedDuration() }} من المحتوى</li>
                        <li><i class="fas fa-list"></i> <span class="en-text">{{ $course->lessons_count }}</span> درس في <span class="en-text">{{ $sections->count() }}</span> أقسام</li>
                        @if (count($previews) > 0)
                            <li><i class="fas fa-unlock"></i> <span class="en-text">{{ count($previews) }}</span> معاينة مجانية</li>
                        @endif
                        <li><i class="fas fa-signal"></i> المستوى: {{ $course->level->label() }}</li>
                        <li><i class="fas fa-mobile-alt"></i> الوصول من الجوال والكمبيوتر</li>
                        @if ($course->has_certificate)
                            <li><i class="fas fa-certificate"></i> شهادة إتمام</li>
                        @endif
                        @if ($course->max_students)
                            <li><i class="fas fa-users"></i> عدد المقاعد محدود: <span class="en-text">{{ $course->max_students }}</span></li>
                        @endif
                    </ul>
                </aside>
            </div>
        </div>

        @if ($relatedCourses->isNotEmpty())
            <section class="course-detail-related section-fade-up">
                <div class="course-detail-related-header">
                    <div>
                        <span class="course-detail-related-eyebrow">اقتراحات لك</span>
                        <h3 class="course-detail-related-title">دورات قد تعجبك أيضاً</h3>
                    </div>
                    <a href="{{ route('courses') }}" class="course-detail-related-link">عرض كل الدورات <i class="fas fa-arrow-left"></i></a>
                </div>
                <div class="row g-4" id="related-courses-container">
                    @foreach ($relatedCourses as $related)
                        @include('frontend.partials.course-card-grid', ['course' => $related])
                    @endforeach
                </div>
            </section>
        @endif
    </main>
@endsection

@push('scripts')
<script>
    // Inline preview player: any .js-play-preview loads that preview into the hero player.
    (function () {
        const previews = @json(collect($previews)->keyBy('id'));
        const player = document.querySelector('[data-player]');
        if (!player || !Object.keys(previews).length) return;

        const poster = player.querySelector('[data-poster]');
        const frame = player.querySelector('[data-frame]');
        const items = document.querySelectorAll('.cd-playlist-item');

        const play = (id) => {
            const preview = previews[id];
            if (!preview) return;

            frame.innerHTML = '';
            if (preview.kind === 'embed') {
                const iframe = document.createElement('iframe');
                const glue = preview.src.includes('?') ? '&' : '?';
                iframe.src = `${preview.src}${glue}autoplay=1&rel=0`;
                iframe.title = preview.title;
                iframe.allow = 'autoplay; fullscreen; picture-in-picture';
                iframe.allowFullscreen = true;
                frame.appendChild(iframe);
            } else {
                const video = document.createElement('video');
                video.src = preview.src;
                video.controls = true;
                video.autoplay = true;
                video.playsInline = true;
                frame.appendChild(video);
            }

            poster.hidden = true;
            frame.hidden = false;
            items.forEach((item) => item.classList.toggle('is-playing', item.dataset.preview === id));
        };

        document.querySelectorAll('.js-play-preview').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                play(trigger.dataset.preview);
                const card = document.getElementById('course-preview');
                const top = card.getBoundingClientRect().top + window.scrollY - 110;
                if (Math.abs(window.scrollY - top) > 40) window.scrollTo({ top, behavior: 'smooth' });
            });
        });
    })();
</script>
@endpush
