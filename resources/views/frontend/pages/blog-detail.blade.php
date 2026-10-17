@extends('frontend.layouts.master')

@section('body_class', 'blog-detail-page')

@section('content')
    <header class="blog-detail-hero">
        <div class="container blog-detail-hero-inner">
            @if($post->category)
                <span class="blog-detail-category">{{ $post->category->name }}</span>
            @endif
            <h1 class="blog-detail-hero-title">{{ $post->title }}</h1>
            <div class="blog-detail-meta">
                @if($post->author)
                    <span><i class="far fa-user"></i> {{ $post->author->name }}</span>
                @endif
                @if($post->published_at)
                    <span><i class="far fa-calendar-alt"></i> {{ $post->published_at->translatedFormat('d M Y') }}</span>
                @endif
                @if($post->reading_time)
                    <span><i class="far fa-clock"></i> {{ $post->reading_time }} دقائق قراءة</span>
                @endif
                <span class="en-text"><i class="far fa-eye"></i> {{ number_format($post->views_count ?? 0) }}</span>
            </div>
            <nav class="blog-detail-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <a href="{{ route('blog.index') }}">المدونة</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <span>{{ Str::limit($post->title, 40) }}</span>
            </nav>
        </div>
    </header>

    <main class="container blog-detail-main">
        <div class="row g-4 g-lg-5 blog-detail-layout">
            <div class="col-lg-8">
                <article class="blog-detail-article section-fade-up">
                    @if($post->featured_image)
                        <div class="blog-detail-featured">
                            <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->featured_image_alt ?? $post->title }}" class="blog-detail-featured-img">
                        </div>
                    @endif

                    <div class="blog-post-content">
                        @if($post->excerpt)
                            <p class="blog-detail-lead">{{ $post->excerpt }}</p>
                        @endif
                        {!! $post->content !!}
                    </div>

                    <footer class="blog-detail-article-footer">
                        @if($post->tags->isNotEmpty())
                            <div class="blog-detail-tags">
                                @foreach($post->tags as $tag)
                                    <span class="blog-detail-tag">{{ $tag->name }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="blog-detail-share">
                            <span>شارك المقال:</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('blog.show', $post->slug)) }}" class="blog-detail-share-btn" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('blog.show', $post->slug)) }}&text={{ urlencode($post->title) }}" class="blog-detail-share-btn" target="_blank" rel="noopener noreferrer" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('blog.show', $post->slug)) }}" class="blog-detail-share-btn" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </footer>
                </article>

                @if($post->author)
                    <div class="blog-detail-author section-fade-up">
                        <span class="blog-detail-author-avatar"><i class="fas fa-user-edit"></i></span>
                        <div>
                            <span class="blog-detail-author-label">كتبه</span>
                            <strong class="blog-detail-author-name">{{ $post->author->name }}</strong>
                        </div>
                    </div>
                @endif

                @if($prevPost || $nextPost)
                    <nav class="blog-detail-nav section-fade-up d-flex justify-content-between gap-3 mt-4">
                        @if($prevPost)
                            <a href="{{ route('blog.show', $prevPost->slug) }}" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-chevron-right"></i> {{ Str::limit($prevPost->title, 30) }}
                            </a>
                        @else
                            <span></span>
                        @endif
                        @if($nextPost)
                            <a href="{{ route('blog.show', $nextPost->slug) }}" class="btn btn-outline-light btn-sm">
                                {{ Str::limit($nextPost->title, 30) }} <i class="fas fa-chevron-left"></i>
                            </a>
                        @endif
                    </nav>
                @endif
            </div>

            <aside class="col-lg-4">
                <div class="blog-detail-sidebar sticky-top section-fade-up">
                    <div class="blog-detail-widget">
                        <header class="blog-detail-widget-head">
                            <span class="blog-detail-widget-icon"><i class="fas fa-search"></i></span>
                            <h2>ابحث في المدونة</h2>
                        </header>
                        <form class="blog-detail-sidebar-search" action="{{ route('blog.index') }}" method="get">
                            <input type="search" name="search" placeholder="كلمة البحث..." aria-label="بحث في المدونة">
                            <button type="submit" aria-label="بحث"><i class="fas fa-arrow-left"></i></button>
                        </form>
                    </div>

                    @if($categories->isNotEmpty())
                        <div class="blog-detail-widget">
                            <header class="blog-detail-widget-head">
                                <span class="blog-detail-widget-icon"><i class="fas fa-folder"></i></span>
                                <h2>التصنيفات</h2>
                            </header>
                            <ul class="blog-sidebar-categories">
                                @foreach($categories as $category)
                                    <li>
                                        <a href="{{ route('blog.index') }}?category={{ $category->slug }}" class="blog-sidebar-category">
                                            <span class="blog-sidebar-category-name">{{ $category->name }}</span>
                                            <span class="blog-sidebar-category-count en-text">{{ $category->published_posts_count }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($recentPosts->isNotEmpty())
                        <div class="blog-detail-widget">
                            <header class="blog-detail-widget-head">
                                <span class="blog-detail-widget-icon"><i class="fas fa-newspaper"></i></span>
                                <h2>أحدث المقالات</h2>
                            </header>
                            <div class="blog-sidebar-posts">
                                @foreach($recentPosts as $recentPost)
                                    <a href="{{ route('blog.show', $recentPost->slug) }}" class="blog-sidebar-post">
                                        <img src="{{ $recentPost->featured_image ? $recentPost->featuredImageUrl() : 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=150&q=80' }}" alt="" class="blog-sidebar-post-img">
                                        <div class="blog-sidebar-post-body">
                                            <h3>{{ Str::limit($recentPost->title, 50) }}</h3>
                                            @if($recentPost->published_at)
                                                <span><i class="far fa-calendar-alt"></i> {{ $recentPost->published_at->translatedFormat('d M Y') }}</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="blog-detail-newsletter">
                        <span class="blog-detail-newsletter-icon"><i class="fas fa-envelope-open-text"></i></span>
                        <h3>اشترك في النشرة</h3>
                        <p>احصل على آخر المقالات والكورسات مباشرة في بريدك الإلكتروني.</p>
                        <div class="newsletter-alert" role="alert" aria-live="polite" hidden></div>
                        <form class="blog-detail-newsletter-form js-newsletter-form" action="{{ route('newsletter.subscribe') }}" method="post" data-source="blog-detail">
                            @csrf
                            <input type="email" name="email" placeholder="بريدك الإلكتروني" required autocomplete="email" aria-label="البريد الإلكتروني">
                            <button type="submit">اشترك الآن</button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    </main>
@endsection
