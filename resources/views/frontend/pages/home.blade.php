@extends('frontend.layouts.master')

@push('vendor-styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush

@section('content')
<!-- Main Body -->
    <div id="toast-container"></div>
    <main class="home-main">
        @if ($homeSections->shows('hero'))
            @include('frontend.partials.home-hero')

            <div class="home-hero-bridge" aria-hidden="true"></div>
        @endif


        @if ($homeSections->shows('categories'))
        <!-- Featured Categories -->
        <section class="categories-section {{ $homeSections->zoneClasses('categories') }} section-fade-up">
            <div class="container">
                <div class="categories-header">
                    <div class="categories-header-text">
                        <span class="categories-eyebrow">أبرز المجالات</span>
                        <h2 class="categories-title">استكشف التصنيفات</h2>
                        <p class="categories-subtitle">اختر مجالك وابدأ التعلم مع أفضل الكورسات العربية</p>
                    </div>
                    <a href="{{ route('categories') }}" class="btn categories-view-all d-none d-md-inline-flex">
                        عرض الكل <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>

                <div class="row g-3 g-md-4 categories-grid">
                    @foreach($homeCategories as $category)
                    <div class="col-6 col-md-4 col-lg-2">
                        <a href="{{ route('courses', ['categories' => [$category->slug]]) }}" class="category-item" style="--cat-color: {{ $category->color ?? '#02ad8f' }};">
                            <span class="category-item-icon"><i class="{{ $category->publicIcon() }}"></i></span>
                            <span class="category-item-title">{{ $category->name }}</span>
                            <span class="category-item-meta"><span class="en-text">{{ $category->courses_count }}</span>+ كورس</span>
                            <span class="category-item-arrow"><i class="fas fa-arrow-left"></i></span>
                        </a>
                    </div>
                    @endforeach
                </div>

                <div class="text-center mt-4 d-md-none">
                    <a href="{{ route('categories') }}" class="btn categories-view-all">
                        عرض الكل <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>
            </div>
        </section>
        @endif

        @if ($homeSections->shows('courses'))
        <!-- Popular Courses -->
        <section class="courses-section {{ $homeSections->zoneClasses('courses') }} section-fade-up">
            <div class="container">
                <div class="courses-header">
                    <div class="courses-header-text">
                        <span class="courses-eyebrow">تعلم الآن</span>
                        <h2 class="courses-title">دورات مختارة لك</h2>
                        <p class="courses-subtitle">الدورات المميزة والأحدث في الأكاديمية — ابدأ بدرس مجاني من أي منها</p>
                    </div>
                    <a href="{{ route('courses') }}" class="btn courses-view-all d-none d-md-inline-flex">
                        جميع الكورسات <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>

                <div class="row g-4" id="home-courses-container">
                    @foreach($homeCourses as $course)
                        @include('frontend.partials.course-card-grid', ['course' => $course])
                    @endforeach
                </div>

                <div class="text-center mt-4 d-md-none">
                    <a href="{{ route('courses') }}" class="btn courses-view-all">
                        جميع الكورسات <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>
            </div>
        </section>
        @endif




        @if ($homeSections->shows('team') && $teamMembers->isNotEmpty())
            @include('frontend.partials.home-team')
        @endif

        @if ($homeSections->shows('testimonials') && $testimonials->isNotEmpty())
            @include('frontend.partials.home-testimonials')
        @endif

        @if ($homeSections->shows('newsletter'))
        <!-- Newsletter -->
        <section class="newsletter-section {{ $homeSections->zoneClasses('newsletter') }} section-fade-up">
            <div class="container">
                <div class="newsletter-box">
                    <div class="row g-4 g-lg-5 align-items-center">
                        <div class="col-lg-6">
                            <div class="newsletter-content">
                                <span class="newsletter-eyebrow"><i class="fas fa-envelope-open-text"></i> ابقَ على اطلاع</span>
                                <h2 class="newsletter-title">انضم لنشرتنا البريدية</h2>
                                <p class="newsletter-desc">اشترك ليصلك آخر الأخبار عن الكورسات الجديدة، نصائح تعليمية حصرية، وعروض الخصومات قبل الجميع.</p>

                                <ul class="newsletter-benefits">
                                    <li>
                                        <span class="newsletter-benefit-icon"><i class="fas fa-tags"></i></span>
                                        <span>خصومات حصرية تصل إلى <strong>40%</strong> للمشتركين</span>
                                    </li>
                                    <li>
                                        <span class="newsletter-benefit-icon"><i class="fas fa-book-open"></i></span>
                                        <span>دروس مجانية وموارد تعليمية أسبوعياً</span>
                                    </li>
                                    <li>
                                        <span class="newsletter-benefit-icon"><i class="fas fa-bell"></i></span>
                                        <span>تنبيهات فورية عند إطلاق كورسات جديدة</span>
                                    </li>
                                </ul>

                                <div class="newsletter-trust">
                                    <div class="newsletter-trust-item">
                                        <strong>+25K</strong>
                                        <span>مشترك نشط</span>
                                    </div>
                                    <div class="newsletter-trust-divider" aria-hidden="true"></div>
                                    <div class="newsletter-trust-item">
                                        <strong>أسبوعياً</strong>
                                        <span>محتوى جديد</span>
                                    </div>
                                    <div class="newsletter-trust-divider" aria-hidden="true"></div>
                                    <div class="newsletter-trust-item">
                                        <strong>0%</strong>
                                        <span>رسائل مزعجة</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="newsletter-form-card">
                                <div class="newsletter-form-header">
                                    <span class="newsletter-form-badge">مجاني</span>
                                    <h3 class="newsletter-form-title">سجّل بريدك الآن</h3>
                                    <p class="newsletter-form-subtitle">اشترك ليصلك جديد الدورات والمقالات أولاً بأول</p>
                                </div>
                                <div class="newsletter-alert" role="alert" aria-live="polite" hidden></div>
                                <form class="newsletter-form js-newsletter-form" action="{{ route('newsletter.subscribe') }}" method="post" data-source="home">
                                    @csrf
                                    <label class="visually-hidden" for="newsletter-email">البريد الإلكتروني</label>
                                    <div class="newsletter-input-wrap">
                                        <i class="fas fa-envelope newsletter-input-icon" aria-hidden="true"></i>
                                        <input type="email" id="newsletter-email" name="email" class="newsletter-input" placeholder="أدخل بريدك الإلكتروني" autocomplete="email" required>
                                    </div>
                                    <button type="submit" class="newsletter-submit">
                                        اشترك الآن <i class="fas fa-arrow-left"></i>
                                    </button>
                                </form>
                                <p class="newsletter-privacy">
                                    <i class="fas fa-lock"></i>
                                    نحترم خصوصيتك — لن نشارك بريدك مع أي طرف ثالث.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if ($homeSections->shows('blog'))
        <!-- Latest Blog Section -->
        <section class="blog-section {{ $homeSections->zoneClasses('blog') }} section-fade-up">
            <div class="container">
                <div class="blog-header">
                    <div class="blog-header-text">
                        <span class="blog-eyebrow">المدونة التعليمية</span>
                        <h2 class="blog-section-title">آخر المقالات والأخبار</h2>
                        <p class="blog-section-subtitle">مقالات عملية ونصائح من خبراء لتطوير مهاراتك ومواكبة أحدث التوجهات</p>
                    </div>
                    <a href="{{ route('blog.index') }}" class="btn blog-view-all d-none d-md-inline-flex">
                        جميع المقالات <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>

                <div class="blog-swiper-wrap">
                    <button class="blog-swiper-btn blog-swiper-prev" aria-label="السابق">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <div class="swiper blog-swiper">
                        <div class="swiper-wrapper">
                            @forelse($blogPosts ?? [] as $post)
                                <div class="swiper-slide">
                                    <x-frontend.blog-card :post="$post" />
                                </div>
                            @empty
                                <div class="swiper-slide">
                                    <article class="blog-card">
                                        <div class="blog-card-body p-4 text-center">
                                            <p class="mb-0 text-secondary">لا توجد مقالات منشورة حالياً.</p>
                                        </div>
                                    </article>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <button class="blog-swiper-btn blog-swiper-next" aria-label="التالي">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                </div>

                <div class="swiper-pagination blog-swiper-pagination"></div>

                <div class="text-center mt-4 d-md-none">
                    <a href="{{ route('blog.index') }}" class="btn blog-view-all">جميع المقالات <i class="fas fa-arrow-left ms-2"></i></a>
                </div>
            </div>
        </section>
        @endif

    </main>

    <!-- Footer -->
@endsection

@push('vendor-scripts')
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
@endpush

@push('scripts')
<script>
        // Init Hero Swiper — behaviour comes from the admin panel (data-hero-settings)
        const heroEl = document.querySelector('.hero-swiper');
        if (heroEl) {
            const hero = JSON.parse(heroEl.dataset.heroSettings || '{}');
            const heroSlides = heroEl.querySelectorAll('.hero-slide');
            const heroLabels = Array.from(heroSlides, (slide) => slide.dataset.bulletLabel || '');
            const heroCycles = heroSlides.length > 1;
            const heroEffects = {
                fade: { effect: 'fade', fadeEffect: { crossFade: true } },
                slide: { effect: 'slide' },
                creative: {
                    effect: 'creative',
                    creativeEffect: {
                        prev: { translate: ['-20%', 0, -1], opacity: 0 },
                        next: { translate: ['100%', 0, 0] },
                    },
                },
            };

            new Swiper(heroEl, {
                ...(heroEffects[hero.effect] || heroEffects.fade),
                speed: hero.speed || 900,
                loop: heroCycles && hero.loop !== false,
                allowTouchMove: heroCycles,
                autoplay: heroCycles && hero.autoplay !== false
                    ? {
                        delay: hero.autoplay_delay || 6000,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: hero.pause_on_hover !== false
                    }
                    : false,
                pagination: {
                    el: '.hero-pagination',
                    clickable: true,
                    renderBullet: (index, className) => {
                        const label = document.createElement('span');
                        label.className = 'hero-bullet-label';
                        label.textContent = heroLabels[index] || '';

                        return `<span class="${className}"><span class="hero-bullet-inner"></span>${label.outerHTML}</span>`;
                    }
                },
                navigation: {
                    nextEl: '.hero-next',
                    prevEl: '.hero-prev'
                },
                on: {
                    autoplayTimeLeft(s, time, progress) {
                        const fill = document.querySelector('.hero-progress-fill');
                        if (fill) fill.style.width = `${(1 - progress) * 100}%`;
                    },
                    slideChangeTransitionStart() {
                        document.querySelectorAll('.hero-slide .hero-content > *').forEach(el => {
                            el.style.animation = 'none';
                            el.offsetHeight;
                            el.style.animation = '';
                        });
                    }
                }
            });
        }

        // Init Team Swiper (only when the admin picks the slider layout)
        const teamEl = document.querySelector('.team-swiper');
        if (teamEl) {
            const columns = Number(teamEl.dataset.columns) || 4;
            new Swiper(teamEl, {
                slidesPerView: 1,
                spaceBetween: 24,
                loop: teamEl.querySelectorAll('.swiper-slide').length > columns,
                autoplay: teamEl.dataset.autoplay === 'true'
                    ? { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true }
                    : false,
                pagination: { el: '.team-swiper-pagination', clickable: true },
                navigation: { nextEl: '.team-swiper-next', prevEl: '.team-swiper-prev' },
                breakpoints: {
                    576: { slidesPerView: Math.min(2, columns) },
                    992: { slidesPerView: Math.min(3, columns) },
                    1200: { slidesPerView: columns }
                }
            });
        }

        // Init Testimonials Swiper (only when the admin picks the slider layout)
        const testimonialsEl = document.querySelector('.testimonials-swiper');
        if (testimonialsEl) {
            const columns = Number(testimonialsEl.dataset.columns) || 3;
            new Swiper(testimonialsEl, {
                slidesPerView: 1,
                spaceBetween: 24,
                loop: testimonialsEl.querySelectorAll('.swiper-slide').length > columns,
                autoplay: testimonialsEl.dataset.autoplay === 'true'
                    ? { delay: 5500, disableOnInteraction: false, pauseOnMouseEnter: true }
                    : false,
                pagination: { el: '.testimonials-swiper-pagination', clickable: true },
                navigation: { nextEl: '.testimonials-swiper-next', prevEl: '.testimonials-swiper-prev' },
                breakpoints: {
                    768: { slidesPerView: Math.min(2, columns) },
                    992: { slidesPerView: columns }
                }
            });
        }

        // Init Blog Swiper
        if (document.querySelector('.blog-swiper')) {
            new Swiper('.blog-swiper', {
                slidesPerView: 1,
                spaceBetween: 24,
                loop: true,
                autoplay: { delay: 5000, disableOnInteraction: false },
                pagination: { el: '.blog-swiper-pagination', clickable: true },
                navigation: { nextEl: '.blog-swiper-next', prevEl: '.blog-swiper-prev' },
                breakpoints: {
                    768:  { slidesPerView: 2 },
                    992:  { slidesPerView: 3 }
                }
            });
        }
    </script>
@endpush
