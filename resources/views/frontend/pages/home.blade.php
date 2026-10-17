@extends('frontend.layouts.master')

@push('vendor-styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
@endpush

@section('content')
<!-- Main Body -->
    <div id="toast-container"></div>
    <main class="home-main">
        @include('frontend.partials.home-hero')

        <div class="home-hero-bridge" aria-hidden="true"></div>


        <!-- Featured Categories -->
        <section class="categories-section home-zone home-zone--base section-fade-up">
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

        <!-- Popular Courses -->
        <section class="courses-section home-zone home-zone--alt section-fade-up">
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




        <!-- Team Slider Section -->
        <section class="team-section home-zone home-zone--base section-fade-up">
            <div class="container">
                <div class="team-header text-center">
                    <span class="team-eyebrow">شركاء النجاح</span>
                    <h2 class="team-title">فريق الخبراء</h2>
                    <p class="team-subtitle mx-auto">مجموعة من أفضل المدربين في الوطن العربي، يجمعهم شغف نقل خبراتهم العملية.</p>
                </div>

                <div class="team-swiper-wrap">
                    <button class="team-swiper-btn team-swiper-prev" aria-label="السابق">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <div class="swiper team-swiper">
                        <div class="swiper-wrapper">

                            <div class="swiper-slide">
                                <article class="team-card" style="--team-color: #02ad8f;">
                                    <div class="team-card-avatar">
                                        <span class="team-card-avatar-inner"><i class="fas fa-user-tie"></i></span>
                                    </div>
                                    <h3 class="team-card-name">م. أحمد سعيد</h3>
                                    <p class="team-card-role en-text">Senior Front-end Engineer</p>
                                    <div class="team-card-rating en-text" aria-label="تقييم 4.9 من 5">
                                        <span class="team-rating-stars">
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                        </span>
                                        <span class="team-rating-value">4.9</span>
                                    </div>
                                    <p class="team-card-bio">خبير في بناء وتطوير واجهات الويب الحديثة بأحدث التقنيات.</p>
                                    <div class="team-card-social">
                                        <a href="#" class="team-social-btn" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                                        <a href="#" class="team-social-btn" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                                    </div>
                                </article>
                            </div>

                            <div class="swiper-slide">
                                <article class="team-card" style="--team-color: #33a4da;">
                                    <div class="team-card-avatar">
                                        <span class="team-card-avatar-inner"><i class="fas fa-paint-brush"></i></span>
                                    </div>
                                    <h3 class="team-card-name">سارة محمد</h3>
                                    <p class="team-card-role en-text">UX/UI Designer</p>
                                    <div class="team-card-rating en-text" aria-label="تقييم 4.8 من 5">
                                        <span class="team-rating-stars">
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                        </span>
                                        <span class="team-rating-value">4.8</span>
                                    </div>
                                    <p class="team-card-bio">متخصصة في تصميم تجربة المستخدم وواجهات الجوال الحديثة.</p>
                                    <div class="team-card-social">
                                        <a href="#" class="team-social-btn" aria-label="Behance"><i class="fab fa-behance"></i></a>
                                        <a href="#" class="team-social-btn" aria-label="Dribbble"><i class="fab fa-dribbble"></i></a>
                                    </div>
                                </article>
                            </div>

                            <div class="swiper-slide">
                                <article class="team-card" style="--team-color: #1a83c1;">
                                    <div class="team-card-avatar">
                                        <span class="team-card-avatar-inner"><i class="fas fa-robot"></i></span>
                                    </div>
                                    <h3 class="team-card-name">عمر مصطفى</h3>
                                    <p class="team-card-role en-text">Machine Learning Engineer</p>
                                    <div class="team-card-rating en-text" aria-label="تقييم 4.9 من 5">
                                        <span class="team-rating-stars">
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                        </span>
                                        <span class="team-rating-value">4.9</span>
                                    </div>
                                    <p class="team-card-bio">شغوف بعلوم البيانات والذكاء الاصطناعي وبناء نماذج توليدية متقدمة.</p>
                                    <div class="team-card-social">
                                        <a href="#" class="team-social-btn" aria-label="GitHub"><i class="fab fa-github"></i></a>
                                        <a href="#" class="team-social-btn" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                                    </div>
                                </article>
                            </div>

                            <div class="swiper-slide">
                                <article class="team-card" style="--team-color: #45bec4;">
                                    <div class="team-card-avatar">
                                        <span class="team-card-avatar-inner"><i class="fas fa-chart-pie"></i></span>
                                    </div>
                                    <h3 class="team-card-name">طارق زياد</h3>
                                    <p class="team-card-role en-text">Digital Marketing Expert</p>
                                    <div class="team-card-rating en-text" aria-label="تقييم 4.5 من 5">
                                        <span class="team-rating-stars">
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i>
                                        </span>
                                        <span class="team-rating-value">4.5</span>
                                    </div>
                                    <p class="team-card-bio">خبير في التسويق الرقمي وإدارة الحملات الإعلانية لتحقيق أعلى عائد.</p>
                                    <div class="team-card-social">
                                        <a href="#" class="team-social-btn" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                                        <a href="#" class="team-social-btn" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                                    </div>
                                </article>
                            </div>

                            <div class="swiper-slide">
                                <article class="team-card" style="--team-color: #1a83c1;">
                                    <div class="team-card-avatar">
                                        <span class="team-card-avatar-inner"><i class="fas fa-mobile-alt"></i></span>
                                    </div>
                                    <h3 class="team-card-name">م. خالد أحمد</h3>
                                    <p class="team-card-role en-text">Flutter / Mobile Developer</p>
                                    <div class="team-card-rating en-text" aria-label="تقييم 5.0 من 5">
                                        <span class="team-rating-stars">
                                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                        </span>
                                        <span class="team-rating-value">5.0</span>
                                    </div>
                                    <p class="team-card-bio">متخصص في تطوير تطبيقات الجوال بـ Flutter وDart للـ iOS والـ Android.</p>
                                    <div class="team-card-social">
                                        <a href="#" class="team-social-btn" aria-label="GitHub"><i class="fab fa-github"></i></a>
                                        <a href="#" class="team-social-btn" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                                    </div>
                                </article>
                            </div>

                        </div>
                    </div>

                    <button class="team-swiper-btn team-swiper-next" aria-label="التالي">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                </div>

                <div class="swiper-pagination team-swiper-pagination"></div>

                <div class="text-center team-footer-cta">
                    <a href="{{ route('who-we-are') }}" class="btn team-view-all">
                        تعرف على كامل الفريق <i class="fas fa-arrow-left ms-2"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="testimonials-section home-zone home-zone--alt section-fade-up">
            <div class="container">
                <div class="testimonials-header text-center">
                    <span class="testimonials-eyebrow">قصص نجاح</span>
                    <h2 class="testimonials-title">آراء المتعلمين</h2>
                    <p class="testimonials-subtitle mx-auto">تجارب حقيقية من متعلمين غيّروا مساراتهم المهنية بفضل محتوى عملي ومحدّث</p>
                </div>

                <div class="row g-4 testimonials-grid">
                    <div class="col-md-6 col-lg-4">
                        <blockquote class="testimonial-card" style="--testimonial-color: #02ad8f;">
                            <span class="testimonial-quote-icon" aria-hidden="true"><i class="fas fa-quote-right"></i></span>
                            <div class="testimonial-avatar">
                                <span class="testimonial-avatar-inner"><i class="fas fa-user"></i></span>
                            </div>
                            <p class="testimonial-text">بفضل الكورسات هنا، استطعت تغيير مساري المهني والعمل كمطور واجهات أمامية في شركة عالمية بوقت قياسي.</p>
                            <div class="testimonial-rating en-text" aria-label="تقييم 5 من 5">
                                <span class="testimonial-stars">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                </span>
                            </div>
                            <footer class="testimonial-author">
                                <cite class="testimonial-name">أحمد محمود</cite>
                                <span class="testimonial-role en-text">Front-end Developer</span>
                            </footer>
                        </blockquote>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <blockquote class="testimonial-card" style="--testimonial-color: #33a4da;">
                            <span class="testimonial-quote-icon" aria-hidden="true"><i class="fas fa-quote-right"></i></span>
                            <div class="testimonial-avatar">
                                <span class="testimonial-avatar-inner"><i class="fas fa-user-graduate"></i></span>
                            </div>
                            <p class="testimonial-text">منصة رائعة، المحتوى مشروح بطريقة مبسطة وعملية. الكورسات يتم تحديثها باستمرار لتطابق متطلبات السوق.</p>
                            <div class="testimonial-rating en-text" aria-label="تقييم 4.5 من 5">
                                <span class="testimonial-stars">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                </span>
                            </div>
                            <footer class="testimonial-author">
                                <cite class="testimonial-name">سارة خليل</cite>
                                <span class="testimonial-role en-text">UI/UX Designer</span>
                            </footer>
                        </blockquote>
                    </div>

                    <div class="col-md-6 col-lg-4">
                        <blockquote class="testimonial-card" style="--testimonial-color: #1a83c1;">
                            <span class="testimonial-quote-icon" aria-hidden="true"><i class="fas fa-quote-right"></i></span>
                            <div class="testimonial-avatar">
                                <span class="testimonial-avatar-inner"><i class="fas fa-laptop-code"></i></span>
                            </div>
                            <p class="testimonial-text">المشاريع العملية والدعم المستمر من المدربين ساعدوني على بناء portfolio قوي والحصول على وظيفة خلال شهرين فقط.</p>
                            <div class="testimonial-rating en-text" aria-label="تقييم 5 من 5">
                                <span class="testimonial-stars">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                </span>
                            </div>
                            <footer class="testimonial-author">
                                <cite class="testimonial-name">محمد العلي</cite>
                                <span class="testimonial-role en-text">Data Analyst</span>
                            </footer>
                        </blockquote>
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter -->
        <section class="newsletter-section home-zone home-zone--base section-fade-up">
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

        <!-- Latest Blog Section -->
        <section class="blog-section home-zone home-zone--alt home-zone--last section-fade-up">
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

    </main>

    <!-- Footer -->
@endsection

@push('vendor-scripts')
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
@endpush

@push('scripts')
<script>
        // Init Hero Swiper
        const heroSwiper = new Swiper('.hero-swiper', {
            effect: 'fade',
            fadeEffect: { crossFade: true },
            speed: 900,
            loop: true,
            autoplay: {
                delay: 6000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            },
            pagination: {
                el: '.hero-pagination',
                clickable: true,
                renderBullet: (index, className) =>
                    `<span class="${className}"><span class="hero-bullet-inner"></span><span class="hero-bullet-label">${['الأكاديمية', 'الموشن', 'الدورات', 'ابدأ مجاناً'][index]}</span></span>`
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

        // Init Team Swiper
        const teamSwiper = new Swiper('.team-swiper', {
            slidesPerView: 1,
            spaceBetween: 24,
            loop: true,
            autoplay: { delay: 4500, disableOnInteraction: false },
            pagination: { el: '.team-swiper-pagination', clickable: true },
            navigation: { nextEl: '.team-swiper-next', prevEl: '.team-swiper-prev' },
            breakpoints: {
                576:  { slidesPerView: 2 },
                992:  { slidesPerView: 3 },
                1200: { slidesPerView: 4 }
            }
        });

        // Init Blog Swiper
        const blogSwiper = new Swiper('.blog-swiper', {
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
    </script>
@endpush
