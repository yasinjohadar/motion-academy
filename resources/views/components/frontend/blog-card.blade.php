{{--
    Blog card — a storyboard frame: film perforations on the edge, the reading
    time as a timecode, the category as the "scene", keyframes on hover.

    Usage: <x-frontend.blog-card :post="$post" />
--}}
@props(['post'])

@php
    /** @var \App\Models\BlogPost $post */
    $color = $post->category?->color ?? '#33a4da';
    $image = $post->featured_image ? $post->featuredImageUrl() : null;
    $url = route('blog.show', $post->slug);
    $minutes = max(1, (int) ($post->reading_time ?: 1));
    $excerpt = \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags((string) ($post->excerpt ?: $post->content)))), 120);
    $author = $post->author?->name;
@endphp

<article {{ $attributes->class('sb-card') }} style="--c: {{ $color }};">
    <a href="{{ $url }}" class="sb-frame" aria-label="{{ $post->title }}">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $post->featured_image_alt ?? '' }}" loading="lazy" class="sb-cover">
        @else
            <span class="sb-art" aria-hidden="true">
                <i class="fas fa-feather-alt"></i>
                <span class="sb-art-line"></span>
            </span>
        @endif
        <span class="sb-perf" aria-hidden="true"></span>
        @if ($post->category)
            <span class="sb-scene">{{ $post->category->name }}</span>
        @endif
        <span class="sb-rec en-text"><span class="sb-dot"></span>00:{{ str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) }}:00</span>
        @if ($post->is_featured)
            <span class="sb-star" title="مقال مميز"><i class="fas fa-star"></i></span>
        @endif
    </a>

    <div class="sb-body">
        <div class="sb-meta">
            @if ($post->published_at)
                <span><i class="far fa-calendar"></i> {{ $post->published_at->translatedFormat('d M Y') }}</span>
            @endif
            <span><i class="far fa-clock"></i> {{ $minutes }} د قراءة</span>
        </div>

        <h3 class="sb-title"><a href="{{ $url }}">{{ $post->title }}</a></h3>

        @if ($excerpt !== '')
            <p class="sb-excerpt">{{ $excerpt }}</p>
        @endif

        <div class="sb-foot">
            <span class="sb-author">
                <span class="sb-avatar">{{ $author ? mb_substr($author, 0, 1) : 'M' }}</span>
                {{ $author ?? 'فريق التحرير' }}
            </span>
            <a href="{{ $url }}" class="sb-go" aria-label="اقرأ المقال"><i class="fas fa-arrow-left"></i></a>
        </div>
    </div>

    <span class="sb-keys" aria-hidden="true"><i></i><i></i><i></i></span>
</article>
