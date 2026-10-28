<blockquote class="testimonial-card" style="--testimonial-color: {{ $testimonial->accentColor() }};">
    <span class="testimonial-quote-icon" aria-hidden="true"><i class="fas fa-quote-right"></i></span>
    <div class="testimonial-avatar">
        <span class="testimonial-avatar-inner">
            @if ($avatar = $testimonial->avatarUrl())
                <img src="{{ $avatar }}" alt="{{ $testimonial->name }}" loading="lazy">
            @else
                <i class="{{ $testimonial->icon ?: \App\Models\Testimonial::DEFAULT_ICON }}"></i>
            @endif
        </span>
    </div>
    <p class="testimonial-text">{{ $testimonial->content }}</p>
    @if ($testimonialSettings['show_rating'])
        <div class="testimonial-rating en-text" aria-label="تقييم {{ (float) $testimonial->rating }} من 5">
            <span class="testimonial-stars">
                @foreach ($testimonial->starIcons() as $star)<i class="{{ $star }}"></i>@endforeach
            </span>
        </div>
    @endif
    <footer class="testimonial-author">
        <cite class="testimonial-name">{{ $testimonial->name }}</cite>
        @if ($subtitle = $testimonial->subtitle())
            <span class="testimonial-role">{{ $subtitle }}</span>
        @endif
        @if ($testimonial->course)
            <a href="{{ route('courses.show', $testimonial->course->slug) }}" class="testimonial-course">
                <i class="fas fa-graduation-cap"></i> {{ $testimonial->course->title }}
            </a>
        @endif
    </footer>
</blockquote>
