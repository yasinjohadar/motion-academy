@extends('frontend.layouts.master')

@section('body_class', 'categories-page')

@section('content')
<!-- Page Hero -->
    <section class="categories-page-hero section-fade-up">
        <div class="container categories-page-hero-inner">
            <span class="categories-page-hero-eyebrow"><i class="fas fa-th-large"></i> مجالات التعلم</span>
            <h1 class="categories-page-hero-title">استكشف التصنيفات</h1>
            <p class="categories-page-hero-desc">اختر المجال الذي يناسب طموحاتك وابدأ رحلتك مع أفضل المدربين في الوطن العربي.</p>

            <div class="categories-page-hero-stats">
                <div class="categories-hero-stat">
                    <strong class="en-text">{{ $stats['total_categories'] }}</strong>
                    <span>تصنيفات</span>
                </div>
                <div class="categories-hero-stat">
                    <strong class="en-text">{{ $stats['total_courses'] }}+</strong>
                    <span>كورس</span>
                </div>
                <div class="categories-hero-stat">
                    <strong class="en-text">{{ $stats['total_instructors'] }}+</strong>
                    <span>مدرب</span>
                </div>
            </div>

            <nav class="categories-page-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <span>التصنيفات</span>
            </nav>
        </div>
    </section>

    <main class="container categories-page-main">
        <header class="categories-page-intro section-fade-up">
            <div>
                <span class="categories-page-intro-eyebrow">اختر مجالك</span>
                <h2 class="categories-page-intro-title">جميع التصنيفات المتاحة</h2>
                <p class="categories-page-intro-desc">انقر على أي تصنيف لاستعراض الكورسات المتاحة فيه مباشرة.</p>
            </div>
        </header>

        <div class="row g-4 section-fade-up" id="categories-grid">
            @foreach($categories as $category)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('courses', ['categories' => [$category->slug]]) }}" class="category-page-card category-card-interactive text-decoration-none" id="{{ $category->slug }}" style="--cat-color: {{ $category->color ?? '#02ad8f' }};">
                        <span class="category-page-card-icon"><i class="{{ $category->publicIcon() }}"></i></span>
                        <h3 class="category-page-card-title">{{ $category->name }}</h3>
                        <p class="category-page-card-desc">{{ \Illuminate\Support\Str::limit(trim(html_entity_decode(strip_tags((string) $category->description))), 140) }}</p>
                        <span class="category-page-card-meta"><span class="en-text">{{ $category->courses_count }}</span> كورس</span>
                        <span class="category-page-card-action">استعراض الكورسات <i class="fas fa-arrow-left"></i></span>
                    </a>
                </div>
            @endforeach
        </div>
    </main>
@endsection
