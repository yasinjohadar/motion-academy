@extends('frontend.layouts.master')

@section('body_class', 'about-page')

@section('content')
<!-- Page Hero -->
    <section class="about-page-hero section-fade-up">
        <div class="container about-page-hero-inner">
            <span class="about-page-hero-eyebrow"><i class="fas fa-graduation-cap"></i> حول {{ $siteSettings['site_name'] }}</span>
            <h1 class="about-page-hero-title">قصتنا.. شغف بالتعليم</h1>
            <p class="about-page-hero-desc">منذ انطلاقتنا، سعينا دائماً إلى إعادة صياغة مفهوم التعليم الإلكتروني في العالم العربي.</p>

            <div class="about-page-hero-stats">
                <div class="about-hero-stat">
                    <strong class="en-text">2019</strong>
                    <span>سنة التأسيس</span>
                </div>
                <div class="about-hero-stat">
                    <strong class="en-text">15+</strong>
                    <span>دولة عربية</span>
                </div>
                <div class="about-hero-stat">
                    <strong class="en-text">100K+</strong>
                    <span>متعلم</span>
                </div>
            </div>

            <nav class="about-page-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <span>حول المنصة</span>
            </nav>
        </div>
    </section>

    <main class="container about-page-main">
        <div id="toast-container"></div>

        <!-- Story Section -->
        <section class="about-story section-fade-up">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-6">
                    <div class="about-story-media" role="img" aria-label="صورة تعبيرية عن قصة {{ $siteSettings['site_name'] }}">
                        <span class="about-story-media-badge"><i class="fas fa-play"></i> قصتنا</span>
                        <span class="about-story-media-icon"><i class="fas fa-graduation-cap"></i></span>
                        <p class="about-story-media-caption">من فكرة بسيطة إلى منصة تعليمية عربية رائدة</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="about-story-content">
                        <span class="about-story-eyebrow">البداية</span>
                        <h2 class="about-story-title">كيف بدأنا؟</h2>
                        <p class="about-story-text">بدأت فكرتنا عندما لاحظنا وجود فجوة حقيقية بين التعليم الأكاديمي التقليدي واحتياجات سوق العمل العربي الفعلي. قررنا بناء منصة لا تقدم «معلومات» فحسب، بل «مهارات» حقيقية وتطبيقية.</p>
                        <p class="about-story-text">اليوم، نفخر بأننا وجهة لمئات الآلاف من الطلاب من مختلف الدول العربية، نسعى جاهدين مع نخبة من خيرة الخبراء في مجالاتهم لنقل خبراتهم الحقيقية بكل شفافية.</p>
                        <div class="about-story-actions">
                            <a href="{{ route('courses') }}" class="about-btn-primary">تصفح كورساتنا <i class="fas fa-arrow-left"></i></a>
                            <a href="{{ route('who-we-are') }}" class="about-btn-outline">تعرف على الفريق</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vision & Mission -->
        <section class="about-values section-fade-up">
            <header class="about-section-header">
                <span class="about-section-eyebrow">رؤيتنا ورسالتنا</span>
                <h2 class="about-section-title">ما الذي نؤمن به؟</h2>
                <p class="about-section-desc">نبني تجربة تعليمية عربية متكاملة تجمع بين الجودة، الوصول، والتطبيق العملي.</p>
            </header>

            <div class="row g-4">
                <div class="col-md-6">
                    <article class="about-value-card h-100" style="--value-color: #02ad8f;">
                        <span class="about-value-icon"><i class="fas fa-eye"></i></span>
                        <h3 class="about-value-title">رؤيتنا</h3>
                        <p class="about-value-text">أن نكون المنصة التعليمية الأولى والخيار الأمثل لكل من يبحث عن تطوير مهاراته وبناء مستقبل مهني ناجح في العالم العربي، من خلال محتوى عالي الجودة ومجتمع تعليمي داعم.</p>
                    </article>
                </div>
                <div class="col-md-6">
                    <article class="about-value-card h-100" style="--value-color: #33a4da;">
                        <span class="about-value-icon"><i class="fas fa-bullseye"></i></span>
                        <h3 class="about-value-title">رسالتنا</h3>
                        <p class="about-value-text">تمكين الأفراد وتزويدهم بالمعرفة والمهارات العملية الحديثة بأسلوب مبسط وبتكلفة معقولة، وتجسير الهوة بين التعليم وسوق العمل عبر دمج التكنولوجيا المتطورة بالتدريب.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Stats Counter -->
        <section class="about-stats stats-section section-fade-up">
            <header class="about-section-header text-center">
                <span class="about-section-eyebrow">إنجازاتنا</span>
                <h2 class="about-section-title">أرقام نفخر بها</h2>
            </header>

            <div class="row g-3 g-md-4 stats-row">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-book-open"></i></div>
                        <h2 class="stat-card-number counter en-text" data-target="250">0</h2>
                        <p class="stat-card-label">+ كورس تعليمي</p>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-users"></i></div>
                        <h2 class="stat-card-number counter en-text" data-target="150000">0</h2>
                        <p class="stat-card-label">+ طالب مستفيد</p>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <h2 class="stat-card-number counter en-text" data-target="50">0</h2>
                        <p class="stat-card-label">+ خبير ومدرب</p>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
                        <h2 class="stat-card-number counter en-text" data-target="12000">0</h2>
                        <p class="stat-card-label">+ ساعة تقييم ومراجعة</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
@endsection
