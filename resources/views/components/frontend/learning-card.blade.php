{{--
    A course the student is taking: cover, progress, where they stopped, and the
    button back into the player.

    Usage: <x-frontend.learning-card :enrollment="$enrollment" :done="$doneCounts[$enrollment->course_id] ?? 0" />
--}}
@props([
    'enrollment',
    'done' => 0,
])

@php
    /** @var \App\Models\CourseEnrollment $enrollment */
    $course = $enrollment->course;
    $color = $course->category?->color ?? '#02ad8f';
    $thumbnail = $course->thumbnailUrl();
    $completed = $enrollment->isCompleted();
    $total = max($course->lessons_count, $done);
@endphp

<article {{ $attributes->class(['sd-course', 'is-completed' => $completed]) }} style="--c: {{ $color }}">
    <a href="{{ route('learn.course', $course->slug) }}" class="sd-course-cover" aria-label="{{ $course->title }}">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="" loading="lazy">
        @else
            <span class="sd-course-art" aria-hidden="true"><i class="{{ $course->category?->publicIcon() ?? 'fas fa-play' }}"></i></span>
        @endif
        <span class="sd-course-play" aria-hidden="true"><i class="fas fa-play"></i></span>
        @if ($completed)
            <span class="sd-course-done"><i class="fas fa-trophy"></i> مكتملة</span>
        @endif
    </a>

    <div class="sd-course-body">
        @if ($course->category)
            <span class="sd-chip">{{ $course->category->name }}</span>
        @endif
        <h3 class="sd-course-title"><a href="{{ route('learn.course', $course->slug) }}">{{ $course->title }}</a></h3>

        <div class="sd-course-progress">
            <div class="sd-bar" role="progressbar" aria-valuenow="{{ $enrollment->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                <span style="width: {{ $enrollment->progress_percent }}%"></span>
            </div>
            <span class="sd-course-percent en-text">{{ $enrollment->progress_percent }}%</span>
        </div>

        <ul class="sd-course-meta">
            <li><i class="far fa-check-circle"></i> <span class="en-text">{{ $done }}</span> من <span class="en-text">{{ $total }}</span> درس</li>
            @if ($enrollment->last_accessed_at)
                <li><i class="far fa-clock"></i> آخر دخول {{ $enrollment->last_accessed_at->diffForHumans() }}</li>
            @elseif ($course->instructor)
                <li><i class="far fa-user"></i> {{ $course->instructor->name }}</li>
            @endif
        </ul>

        @if (! $completed && $enrollment->lastLesson)
            <p class="sd-course-next"><i class="fas fa-play-circle"></i> {{ $enrollment->lastLesson->title }}</p>
        @endif

        <a href="{{ route('learn.course', $course->slug) }}" class="sd-btn {{ $completed ? 'sd-btn-ghost' : '' }}">
            @if ($completed)
                <i class="fas fa-redo"></i> راجع الدورة
            @elseif ($enrollment->progress_percent > 0)
                <i class="fas fa-play"></i> تابع التعلّم
            @else
                <i class="fas fa-rocket"></i> ابدأ الدورة
            @endif
        </a>
    </div>
</article>
