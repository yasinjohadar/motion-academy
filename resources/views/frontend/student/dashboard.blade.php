@extends('frontend.layouts.student')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'صباح الخير' : ($hour < 18 ? 'نهارك سعيد' : 'مساء الخير');
    $firstName = \Illuminate\Support\Str::of($account['name'] ?? '')->trim()->explode(' ')->first();
@endphp

@section('sd_title', $greeting.'، '.$firstName.' 👋')
@section('sd_subtitle', $current ? 'لنكمل من حيث توقفت — كل درس صغير يقرّبك من هدفك.' : 'هنا تتابع دوراتك وتقدّمك وطلباتك في مكان واحد.')

@section('student')
    {{-- Continue where you left off --}}
    @if ($current)
        @php($course = $current->course)
        <section class="sd-resume" style="--c: {{ $course->category?->color ?? '#02ad8f' }}">
            <a href="{{ route('learn.course', $course->slug) }}" class="sd-resume-cover" aria-hidden="true" tabindex="-1">
                @if ($course->thumbnailUrl())
                    <img src="{{ $course->thumbnailUrl() }}" alt="" loading="lazy">
                @else
                    <span class="sd-course-art"><i class="{{ $course->category?->publicIcon() ?? 'fas fa-play' }}"></i></span>
                @endif
                <span class="sd-course-play"><i class="fas fa-play"></i></span>
            </a>
            <div class="sd-resume-body">
                <span class="sd-resume-label"><i class="ri-play-circle-line"></i> تابع من حيث توقفت</span>
                <h2 class="sd-resume-title">{{ $course->title }}</h2>
                @if ($currentLesson)
                    <p class="sd-resume-lesson">
                        <span>الدرس التالي:</span> {{ $currentLesson->title }}
                        @if ($currentLesson->formattedDuration())
                            <span class="en-text sd-muted">· {{ $currentLesson->formattedDuration() }}</span>
                        @endif
                    </p>
                @endif
                <div class="sd-course-progress">
                    <div class="sd-bar"><span style="width: {{ $current->progress_percent }}%"></span></div>
                    <span class="sd-course-percent en-text">{{ $current->progress_percent }}%</span>
                </div>
                <a href="{{ $currentLesson ? route('learn.lesson', [$course->slug, $currentLesson->id]) : route('learn.course', $course->slug) }}" class="sd-btn">
                    <i class="fas fa-play"></i> {{ $current->progress_percent > 0 ? 'تابع التعلّم' : 'ابدأ الآن' }}
                </a>
            </div>
            <x-frontend.progress-ring :percent="$current->progress_percent" :size="92" class="sd-resume-ring" />
        </section>
    @endif

    {{-- Numbers --}}
    <section class="sd-stats" aria-label="إحصاءاتك">
        @foreach ([
            ['icon' => 'ri-book-open-line', 'value' => $stats['active'], 'label' => 'دورة قيد التعلّم', 'tone' => 'green'],
            ['icon' => 'ri-trophy-line', 'value' => $stats['completed'], 'label' => 'دورة مكتملة', 'tone' => 'gold'],
            ['icon' => 'ri-checkbox-circle-line', 'value' => $stats['lessons'], 'label' => 'درس أنهيته', 'tone' => 'blue'],
            ['icon' => 'ri-time-line', 'value' => $stats['minutes'] >= 60 ? round($stats['minutes'] / 60, 1) : $stats['minutes'], 'label' => $stats['minutes'] >= 60 ? 'ساعة تعلّم' : 'دقيقة تعلّم', 'tone' => 'navy'],
        ] as $stat)
            <div class="sd-stat is-{{ $stat['tone'] }}">
                <span class="sd-stat-icon"><i class="{{ $stat['icon'] }}"></i></span>
                <strong class="en-text">{{ $stat['value'] }}</strong>
                <span>{{ $stat['label'] }}</span>
            </div>
        @endforeach
    </section>

    <div class="sd-grid-2">
        {{-- Courses in progress --}}
        <section class="sd-panel">
            <header class="sd-panel-head">
                <h2><i class="ri-book-open-line"></i> دوراتي الحالية</h2>
                @if ($active->isNotEmpty())
                    <a href="{{ route('student.courses') }}" class="sd-link">عرض الكل <i class="fas fa-arrow-left"></i></a>
                @endif
            </header>

            @forelse ($active->take(4) as $enrollment)
                <a href="{{ route('learn.course', $enrollment->course->slug) }}" class="sd-row">
                    <x-frontend.progress-ring :percent="$enrollment->progress_percent" :size="46" />
                    <span class="sd-row-text">
                        <strong>{{ $enrollment->course->title }}</strong>
                        <small><span class="en-text">{{ $doneCounts[$enrollment->course_id] ?? 0 }}</span> من <span class="en-text">{{ $enrollment->course->lessons_count }}</span> درس</small>
                    </span>
                    <i class="fas fa-chevron-left sd-row-go"></i>
                </a>
            @empty
                <div class="sd-empty">
                    <i class="ri-compass-3-line"></i>
                    <strong>لم تبدأ أي دورة بعد</strong>
                    <p>اختر دورة واطلب الانضمام إليها، وستظهر هنا فور قبول طلبك.</p>
                    <a href="{{ route('courses') }}" class="sd-btn"><i class="fas fa-search"></i> تصفّح الدورات</a>
                </div>
            @endforelse
        </section>

        {{-- Notifications --}}
        <section class="sd-panel">
            <header class="sd-panel-head">
                <h2><i class="ri-notification-3-line"></i> آخر الإشعارات</h2>
                <a href="{{ route('student.notifications') }}" class="sd-link">الكل <i class="fas fa-arrow-left"></i></a>
            </header>

            @forelse ($notifications as $notification)
                @include('frontend.student.partials.notification', ['notification' => $notification, 'compact' => true])
            @empty
                <div class="sd-empty is-small">
                    <i class="ri-notification-off-line"></i>
                    <p>لا إشعارات بعد. سنبلغك هنا بقرارات طلباتك وكل جديد.</p>
                </div>
            @endforelse
        </section>
    </div>

    {{-- Pending requests --}}
    @if ($pending->isNotEmpty())
        <section class="sd-panel">
            <header class="sd-panel-head">
                <h2><i class="ri-hourglass-line"></i> طلبات بانتظار الموافقة</h2>
                <a href="{{ route('student.requests') }}" class="sd-link">كل الطلبات <i class="fas fa-arrow-left"></i></a>
            </header>
            @foreach ($pending as $enrollment)
                <div class="sd-row is-static">
                    <span class="sd-status is-pending"><i class="ri-hourglass-line"></i></span>
                    <span class="sd-row-text">
                        <strong>{{ $enrollment->course->title }}</strong>
                        <small>أُرسل {{ $enrollment->created_at?->diffForHumans() }}</small>
                    </span>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Suggestions for a student with no courses yet --}}
    @if ($suggestions->isNotEmpty())
        <section class="sd-panel">
            <header class="sd-panel-head">
                <h2><i class="ri-sparkling-line"></i> دورات قد تعجبك</h2>
                <a href="{{ route('courses') }}" class="sd-link">كل الدورات <i class="fas fa-arrow-left"></i></a>
            </header>
            <div class="row g-4">
                @foreach ($suggestions as $course)
                    <div class="col-md-6 col-xl-4">
                        <x-frontend.course-card :course="$course" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
