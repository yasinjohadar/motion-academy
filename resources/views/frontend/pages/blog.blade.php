@extends('frontend.layouts.master')

@section('body_class', 'blog-page')

@section('content')
    <section class="blog-page-hero section-fade-up">
        <div class="container blog-page-hero-inner">
            <span class="blog-page-hero-eyebrow"><i class="fas fa-pen-nib"></i> محتوى تعليمي</span>
            <h1 class="blog-page-hero-title">المدونة التعليمية</h1>
            <p class="blog-page-hero-desc">مقالات، أخبار، ونصائح تعليمية بأيدي خبراء في مختلف المجالات الرقمية.</p>

            <div class="blog-page-hero-stats">
                <div class="blog-hero-stat">
                    <strong class="en-text">{{ $posts->total() }}+</strong>
                    <span>مقال</span>
                </div>
                <div class="blog-hero-stat">
                    <strong class="en-text">{{ $categories->count() }}</strong>
                    <span>تصنيف</span>
                </div>
                <div class="blog-hero-stat">
                    <strong class="en-text">{{ number_format($totalViews) }}</strong>
                    <span>مشاهدة للمقالات</span>
                </div>
            </div>

            <nav class="blog-page-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <span>المدونة</span>
            </nav>
        </div>
    </section>

    <main class="container blog-page-main">
        @if($featuredPost)
            <article class="blog-featured section-fade-up">
                <a href="{{ route('blog.show', $featuredPost->slug) }}" class="blog-featured-link">
                    <div class="blog-featured-image">
                        <img src="{{ $featuredPost->featured_image ? $featuredPost->featuredImageUrl() : 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80' }}" alt="{{ $featuredPost->title }}">
                    </div>
                    <div class="blog-featured-body">
                        <span class="blog-featured-badge">مقال مميز</span>
                        <h2>{{ $featuredPost->title }}</h2>
                        <p>{{ $featuredPost->excerpt ?? Str::limit(strip_tags($featuredPost->content), 160) }}</p>
                        <span class="blog-featured-cta">اقرأ المقال <i class="fas fa-arrow-left"></i></span>
                    </div>
                </a>
            </article>
        @endif

        <div class="blog-page-toolbar section-fade-up">
            <div class="blog-page-search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="blog-search-input" placeholder="ابحث عن مقال معين..." aria-label="بحث في المدونة">
            </div>
            <div class="blog-page-filters" id="blog-filter-tabs" role="tablist">
                <button type="button" class="blog-filter-chip active" data-filter="all">الكل</button>
                @foreach($categories as $category)
                    <button type="button" class="blog-filter-chip" data-filter="{{ $category->slug }}">{{ $category->name }}</button>
                @endforeach
            </div>
        </div>

        <p class="blog-results-count section-fade-up" id="blog-results-count">عرض <span class="en-text">{{ $posts->count() }}</span> مقالات</p>

        <div class="row g-4" id="blog-posts-grid">
            @forelse($posts as $post)
                <div class="col-md-6 col-lg-4 blog-post-item" data-category="{{ $post->category?->slug ?? 'general' }}" data-title="{{ $post->title }}">
                    <x-frontend.blog-card :post="$post" />
                </div>
            @empty
                <div class="col-12">
                    <div class="blog-empty-state" id="blog-empty-state">
                        <i class="fas fa-search"></i>
                        <h5>لا توجد مقالات منشورة</h5>
                        <p>تابعنا لاحقاً للحصول على محتوى تعليمي جديد.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="blog-empty-state d-none" id="blog-filter-empty-state">
            <i class="fas fa-search"></i>
            <h5>لم يتم العثور على مقالات</h5>
            <p>جرّب كلمات بحث مختلفة أو اختر تصنيفاً آخر.</p>
        </div>

        @if($posts->hasPages())
            <nav class="blog-pagination section-fade-up d-flex justify-content-center mt-4" aria-label="صفحات المدونة">
                {{ $posts->links('pagination::bootstrap-5') }}
            </nav>
        @endif
    </main>
@endsection
