@extends('frontend.layouts.master')

@section('body_class', 'learn-page')
@section('hide_footer', true)

@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\CourseLesson $lesson */
    $previous = $neighbours['previous'];
    $next = $neighbours['next'];
    $courseDone = (bool) $enrollment?->isCompleted();
@endphp

@section('content')
<div class="lp" id="lp"
    data-complete-url="{{ route('learn.complete', $lesson->id) }}"
    data-position-url="{{ route('learn.position', $lesson->id) }}"
    data-completed="{{ $isCompleted ? '1' : '0' }}"
    data-position="{{ $position }}"
    data-duration="{{ $lesson->duration_seconds }}"
    data-next-url="{{ $next ? route('learn.lesson', [$course->slug, $next->id]) : '' }}">

    <header class="lp-top">
        <div class="lp-top-inner">
            <a href="{{ route('student.courses') }}" class="lp-back" title="العودة إلى دوراتي"><i class="fas fa-arrow-right"></i><span>دوراتي</span></a>
            <div class="lp-top-title">
                <small>{{ $course->category?->name ?? 'دورة' }}</small>
                <strong>{{ $course->title }}</strong>
            </div>
            <div class="lp-top-progress" title="تقدّمك في الدورة">
                <div class="sd-bar"><span data-lp-bar style="width: {{ $percent }}%"></span></div>
                <span class="en-text" data-lp-percent>{{ $percent }}%</span>
            </div>
            <button type="button" class="lp-toggle" data-lp-toggle aria-controls="lp-curriculum" aria-expanded="false">
                <i class="ri-list-check-2"></i><span>المنهج</span>
            </button>
        </div>
    </header>

    <div class="lp-body">
        <main class="lp-stage">
            <div class="lp-banner{{ $courseDone ? '' : ' d-none' }}" data-lp-finished role="status">
                <span class="lp-banner-icon">🎉</span>
                <div>
                    <strong>مبروك! أكملت الدورة</strong>
                    <p>أنهيت كل دروس «{{ $course->title }}». يمكنك مراجعة أي درس متى شئت.</p>
                </div>
                <a href="{{ route('student.courses', ['tab' => 'completed']) }}" class="sd-btn is-small">دوراتي المكتملة</a>
            </div>

            {{-- The lesson itself --}}
            @if ($media['kind'] === 'embed')
                <div class="lp-media">
                    <iframe src="{{ $media['src'] }}" title="{{ $lesson->title }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @elseif ($media['kind'] === 'video')
                <div class="lp-media">
                    <video src="{{ $media['src'] }}" controls playsinline preload="metadata" controlsList="nodownload" data-lp-video></video>
                </div>
            @elseif ($media['kind'] === 'missing')
                <div class="lp-media is-empty">
                    <i class="ri-film-line"></i>
                    <p>فيديو هذا الدرس غير متاح حالياً.</p>
                </div>
            @elseif ($media['kind'] === 'file')
                <div class="lp-file">
                    <span class="lp-file-icon"><i class="ri-file-download-line"></i></span>
                    <div>
                        <strong>ملف الدرس</strong>
                        <p>حمّل الملف واعمل عليه، ثم علّم الدرس كمكتمل.</p>
                    </div>
                    @if ($media['attachment'])
                        <a href="{{ $media['attachment'] }}" class="sd-btn" target="_blank" rel="noopener" download><i class="fas fa-download"></i> تحميل</a>
                    @endif
                </div>
            @endif

            <div class="lp-lesson-head">
                <div>
                    <span class="lp-lesson-meta">
                        <i class="{{ $lesson->type->icon() }}"></i> {{ $lesson->section?->title }}
                        · الدرس <span class="en-text">{{ $neighbours['position'] }}</span> من <span class="en-text">{{ $neighbours['total'] }}</span>
                        @if ($lesson->formattedDuration())
                            · <span class="en-text">{{ $lesson->formattedDuration() }}</span>
                        @endif
                    </span>
                    <h1 class="lp-lesson-title">{{ $lesson->title }}</h1>
                </div>

                <form method="post" action="{{ route('learn.complete', $lesson->id) }}" data-lp-complete-form>
                    @csrf
                    <input type="hidden" name="completed" value="{{ $isCompleted ? '0' : '1' }}">
                    <button type="submit" class="lp-complete{{ $isCompleted ? ' is-done' : '' }}" data-lp-complete>
                        <i class="{{ $isCompleted ? 'fas fa-check-circle' : 'far fa-circle' }}"></i>
                        <span>{{ $isCompleted ? 'مكتمل' : 'أكملت الدرس' }}</span>
                    </button>
                </form>
            </div>

            @if (filled($media['content']))
                <article class="lp-content">
                    @if ($media['kind'] !== 'text')
                        <h2 class="lp-content-title"><i class="ri-sticky-note-line"></i> ملاحظات الدرس</h2>
                    @endif
                    {!! $media['content'] !!}
                </article>
            @elseif ($media['kind'] === 'text')
                <article class="lp-content"><p class="sd-muted">لا يوجد محتوى نصي لهذا الدرس بعد.</p></article>
            @endif

            @if ($resources->isNotEmpty())
                <section class="lp-resources" aria-label="مرفقات الدرس">
                    <h2 class="lp-content-title"><i class="ri-attachment-2"></i> مرفقات الدرس <span class="lp-resources-count en-text">{{ $resources->count() }}</span></h2>
                    <div class="lp-resources-grid">
                        @foreach ($resources as $resource)
                            @php($href = $resource->href())
                            @continue(! $href)
                            <a href="{{ $href }}" class="lp-resource is-{{ $resource->type }}" target="_blank" rel="noopener" @if ($resource->isFile()) download="{{ $resource->original_name }}" @endif>
                                <span class="lp-resource-icon"><i class="{{ $resource->icon() }}"></i></span>
                                <span class="lp-resource-body">
                                    <strong>{{ $resource->title }}</strong>
                                    <small>
                                        @if ($resource->isFile())
                                            <span class="en-text">{{ strtoupper($resource->extension() ?? 'FILE') }}</span>@if ($resource->humanSize()) · <span class="en-text">{{ $resource->humanSize() }}</span>@endif
                                        @else
                                            <span class="en-text" dir="ltr">{{ parse_url((string) $resource->url, PHP_URL_HOST) }}</span>
                                        @endif
                                    </small>
                                </span>
                                <i class="lp-resource-go {{ $resource->isFile() ? 'ri-download-2-line' : 'ri-external-link-line' }}"></i>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            <nav class="lp-nav" aria-label="التنقل بين الدروس">
                @if ($previous)
                    <a href="{{ route('learn.lesson', [$course->slug, $previous->id]) }}" class="lp-nav-link">
                        <i class="fas fa-arrow-right"></i>
                        <span><small>السابق</small>{{ $previous->title }}</span>
                    </a>
                @else
                    <span></span>
                @endif
                @if ($next)
                    <a href="{{ route('learn.lesson', [$course->slug, $next->id]) }}" class="lp-nav-link is-next">
                        <span><small>التالي</small>{{ $next->title }}</span>
                        <i class="fas fa-arrow-left"></i>
                    </a>
                @endif
            </nav>
        </main>

        <aside class="lp-curriculum" id="lp-curriculum" data-lp-curriculum aria-label="منهج الدورة">
            <div class="lp-curriculum-head">
                <strong><i class="ri-list-check-2"></i> محتوى الدورة</strong>
                <span><span class="en-text" data-lp-done-count>{{ count($completedIds) }}</span>/<span class="en-text">{{ $neighbours['total'] }}</span> مكتمل</span>
                <button type="button" class="lp-close" data-lp-toggle aria-label="إغلاق"><i class="fas fa-times"></i></button>
            </div>

            @foreach ($sections as $index => $group)
                @php($sectionDone = $group['lessons']->every(fn ($item) => in_array($item->id, $completedIds, true)))
                <details class="lp-section" @if ($group['lessons']->contains('id', $lesson->id) || $index === 0) open @endif>
                    <summary>
                        <span class="lp-section-index en-text">{{ $index + 1 }}</span>
                        <span class="lp-section-title">{{ $group['section']?->title }}</span>
                        @if ($sectionDone)
                            <i class="fas fa-check-circle lp-section-done"></i>
                        @endif
                        <span class="lp-section-count en-text">{{ $group['lessons']->count() }}</span>
                    </summary>
                    <ol>
                        @foreach ($group['lessons'] as $item)
                            @php($itemDone = in_array($item->id, $completedIds, true))
                            <li>
                                <a href="{{ route('learn.lesson', [$course->slug, $item->id]) }}"
                                    class="lp-item{{ $item->id === $lesson->id ? ' is-current' : '' }}{{ $itemDone ? ' is-done' : '' }}"
                                    data-lp-item="{{ $item->id }}" @if ($item->id === $lesson->id) aria-current="page" @endif>
                                    <span class="lp-item-state">
                                        <i class="fas fa-check"></i>
                                        <i class="{{ $item->type->icon() }}"></i>
                                    </span>
                                    <span class="lp-item-title">{{ $item->title }}</span>
                                    @if ($item->formattedDuration())
                                        <span class="lp-item-time en-text">{{ $item->formattedDuration() }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </details>
            @endforeach
        </aside>
        <div class="lp-scrim" data-lp-toggle></div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ $fa }}/js/learn.js"></script>
@endpush
