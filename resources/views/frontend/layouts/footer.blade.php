@php
    $email = $siteSettings['site_email'];
    $phone = $siteSettings['site_phone'];
    $address = $siteSettings['site_address'];
    $footerSocial = [
        ['url' => $siteSettings['facebook_url'] ?? '#', 'icon' => 'fab fa-facebook-f', 'label' => 'Facebook'],
        ['url' => $siteSettings['instagram_url'] ?? '#', 'icon' => 'fab fa-instagram', 'label' => 'Instagram'],
        ['url' => $siteSettings['linkedin_url'] ?? '#', 'icon' => 'fab fa-linkedin-in', 'label' => 'LinkedIn'],
        ['url' => $siteSettings['youtube_url'] ?? '#', 'icon' => 'fab fa-youtube', 'label' => 'YouTube'],
        ['url' => $siteSettings['github_url'] ?? '#', 'icon' => 'fab fa-github', 'label' => 'GitHub'],
    ];
@endphp
<footer class="site-footer">
    <div class="footer-wave">
        <svg viewBox="0 0 1440 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0,40 C360,90 720,0 1080,40 C1260,60 1380,50 1440,40 L1440,80 L0,80 Z" fill="currentColor"/>
        </svg>
    </div>

    <div class="footer-cta-band">
        <div class="container">
            <div class="footer-cta-inner">
                <div class="footer-cta-text">
                    <span class="footer-cta-label"><i class="fas fa-crown me-2"></i>تجربة تعليمية استثنائية</span>
                    <h3 class="footer-cta-title">ابدأ رحلتك نحو <span>التميز المهني</span> اليوم</h3>
                    <p class="footer-cta-desc">ابدأ بدرس مجاني من أي دورة، وحوّل أفكارك إلى محتوى بصري متحرّك</p>
                </div>
                <div class="footer-cta-actions">
                    <a href="{{ route('courses') }}" class="btn footer-cta-btn-primary">استكشف الكورسات <i class="fas fa-arrow-left"></i></a>
                    <a href="{{ route('register') }}" class="btn footer-cta-btn-outline">إنشاء حساب مجاني</a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-main">
        <div class="footer-glow footer-glow-1"></div>
        <div class="footer-glow footer-glow-2"></div>
        <div class="container position-relative">
            <div class="row g-5 footer-grid">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <a href="{{ route('home') }}" class="footer-logo">
                            <img src="{{ $fa }}/images/logo.png" alt="{{ $siteSettings['site_name'] }}" class="footer-logo-img" width="120" height="133" loading="lazy">
                        </a>
                        <p class="footer-brand-desc">
                            أكاديمية متخصصة في التحريك الرقمي والتدريب على برامجه. نبني بيئة تعليمية إبداعية تساعد المتدربين على تطوير مهاراتهم في صناعة المحتوى البصري.
                        </p>
                        <div class="footer-trust-badges">
                            <div class="footer-trust-item"><i class="fas fa-play-circle"></i><span>تعلّم عملي بالفيديو</span></div>
                            <div class="footer-trust-item"><i class="fas fa-unlock"></i><span>دروس مجانية للمعاينة</span></div>
                            <div class="footer-trust-item"><i class="fas fa-clock"></i><span>تعلّم بالسرعة التي تناسبك</span></div>
                        </div>
                        <div class="footer-social">
                            @foreach ($footerSocial as $social)
                                @if (! empty($social['url']) && $social['url'] !== '#')
                                    <a href="{{ $social['url'] }}" class="footer-social-link" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><i class="{{ $social['icon'] }}"></i></a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-2 col-md-3">
                    <h6 class="footer-heading">استكشف</h6>
                    <ul class="footer-links">
                        <li><a href="{{ route('courses') }}"><i class="fas fa-chevron-left"></i> جميع الكورسات</a></li>
                        <li><a href="{{ route('categories') }}"><i class="fas fa-chevron-left"></i> التصنيفات</a></li>
                        <li><a href="{{ route('blog.index') }}"><i class="fas fa-chevron-left"></i> المدونة</a></li>
                        <li><a href="{{ route('who-we-are') }}"><i class="fas fa-chevron-left"></i> فريق الخبراء</a></li>
                        <li><a href="{{ route('cart') }}"><i class="fas fa-chevron-left"></i> سلة التسوق</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-2 col-md-3">
                    <h6 class="footer-heading">المنصة</h6>
                    <ul class="footer-links">
                        <li><a href="{{ route('about') }}"><i class="fas fa-chevron-left"></i> حول {{ $siteSettings['site_name'] }}</a></li>
                        <li><a href="{{ route('who-we-are') }}"><i class="fas fa-chevron-left"></i> من نحن</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> انضم كمدرب</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> الشركاء</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> الوظائف</a></li>
                    </ul>
                </div>

                <div class="col-6 col-lg-2 col-md-3">
                    <h6 class="footer-heading">الدعم</h6>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-left"></i> مركز المساعدة</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> الأسئلة الشائعة</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> سياسة الخصوصية</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> الشروط والأحكام</a></li>
                        <li><a href="#"><i class="fas fa-chevron-left"></i> سياسة الاسترداد</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="footer-heading">تواصل معنا</h6>
                    <ul class="footer-contact">
                        @if (filled($email))
                        <li>
                            <span class="footer-contact-icon"><i class="fas fa-envelope"></i></span>
                            <div>
                                <small>البريد الإلكتروني</small>
                                <a href="mailto:{{ $email }}">{{ $email }}</a>
                            </div>
                        </li>
                        @endif
                        @if (filled($phone))
                        <li>
                            <span class="footer-contact-icon"><i class="fas fa-phone-alt"></i></span>
                            <div>
                                <small>الهاتف</small>
                                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="en-text" dir="ltr">{{ $phone }}</a>
                            </div>
                        </li>
                        @endif
                        @if (filled($address))
                        <li>
                            <span class="footer-contact-icon"><i class="fas fa-map-marker-alt"></i></span>
                            <div>
                                <small>المقر</small>
                                <span>{{ $address }}</span>
                            </div>
                        </li>
                        @endif
                    </ul>
                    <div class="footer-newsletter">
                        <p class="footer-newsletter-label">النشرة البريدية</p>
                        <div class="newsletter-alert newsletter-alert--compact" role="alert" aria-live="polite" hidden></div>
                        <form class="footer-newsletter-form js-newsletter-form" action="{{ route('newsletter.subscribe') }}" method="post" data-source="footer">
                            @csrf
                            <input type="email" name="email" placeholder="بريدك الإلكتروني" required autocomplete="email">
                            <button type="submit" aria-label="اشتراك"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="footer-stats">
                <div class="footer-stat"><strong class="en-text">{{ $footerStats['courses'] }}</strong><span>دورة متاحة</span></div>
                <div class="footer-stat-divider"></div>
                <div class="footer-stat"><strong class="en-text">{{ $footerStats['lessons'] }}</strong><span>درس تعليمي</span></div>
                <div class="footer-stat-divider"></div>
                <div class="footer-stat"><strong class="en-text">{{ $footerStats['categories'] }}</strong><span>مجال تعليمي</span></div>
                <div class="footer-stat-divider"></div>
                <div class="footer-stat"><strong class="en-text">{{ $footerStats['hours'] }}</strong><span>ساعة محتوى</span></div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="footer-bottom-inner">
                <p class="footer-copyright">&copy; {{ date('Y') }} <strong>{{ $siteSettings['site_name'] }}</strong>. جميع الحقوق محفوظة.</p>
                <div class="footer-legal">
                    <a href="#">الخصوصية</a>
                    <span class="footer-legal-sep">|</span>
                    <a href="#">الشروط</a>
                    <span class="footer-legal-sep">|</span>
                    <a href="#">ملفات تعريف الارتباط</a>
                </div>
            </div>
        </div>
    </div>
</footer>
