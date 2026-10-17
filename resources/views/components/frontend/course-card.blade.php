{{--
    Course card — styled like a frame in a motion editor: the cover is the "screen",
    under it a timeline whose playhead sweeps on hover.

    Usage: <x-frontend.course-card :course="$course" />
           <x-frontend.course-card :course="$course" variant="list" />
--}}
@props([
    'course',
    'variant' => 'grid',
])

@php
    /** @var \App\Models\Course $course */
    $color = $course->category?->color ?? '#02ad8f';
    $icon = $course->category?->publicIcon() ?? 'fas fa-play';
    $thumbnail = $course->thumbnailUrl();
    $badge = $course->publicBadge();
    $url = route('courses.show', $course->slug);
    $excerpt = \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags((string) $course->short_description))), 110);
    $original = $course->formattedOriginalPrice();
@endphp

<article {{ $attributes->class(['mc-card', 'mc-card--list' => $variant === 'list']) }} style="--c: {{ $color }};" data-course-id="{{ $course->id }}">
    <a href="{{ $url }}" class="mc-screen" aria-label="{{ $course->title }}">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="" loading="lazy" class="mc-cover">
        @else
            <span class="mc-art" aria-hidden="true">
                <i class="{{ $icon }}"></i>
                <span class="mc-art-shape"></span>
            </span>
        @endif

        @if ($badge)
            <span class="mc-badge">{{ $badge }}</span>
        @endif
        <span class="mc-level"><i class="fas fa-signal"></i> {{ $course->level->label() }}</span>
        <span class="mc-play" aria-hidden="true"><i class="fas fa-play"></i></span>
        <span class="mc-timecode en-text"><i class="far fa-clock"></i> {{ $course->formattedDuration() }}</span>
    </a>

    <div class="mc-timeline" aria-hidden="true">
        <span class="mc-clip mc-clip-1"></span>
        <span class="mc-clip mc-clip-2"></span>
        <span class="mc-clip mc-clip-3"></span>
        <span class="mc-playhead"></span>
    </div>

    <div class="mc-body">
        @if ($course->category)
            <span class="mc-cat"><i class="{{ $icon }}"></i> {{ $course->category->name }}</span>
        @endif

        <h3 class="mc-title"><a href="{{ $url }}">{{ $course->title }}</a></h3>

        @if ($excerpt !== '')
            <p class="mc-excerpt">{{ $excerpt }}</p>
        @endif

        <div class="mc-stats">
            <span><i class="fas fa-film"></i> <b class="en-text">{{ $course->lessons_count }}</b> درس</span>
            @if ($course->instructor)
                <span class="mc-instructor"><span class="mc-avatar">{{ mb_substr($course->instructor->name, 0, 1) }}</span>{{ $course->instructor->name }}</span>
            @endif
        </div>

        <div class="mc-foot">
            <div class="mc-price">
                <strong class="en-text {{ $course->is_free ? 'is-free' : '' }}">{{ $course->formattedPrice() }}</strong>
                @if ($original)
                    <del class="en-text">{{ $original }}</del>
                @endif
            </div>
            <a href="{{ $url }}" class="mc-go" aria-label="عرض الدورة">
                <span>عرض الدورة</span>
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>
</article>
