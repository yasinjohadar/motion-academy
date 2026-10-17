@extends('frontend.layouts.master')

@section('body_class', 'cart-page')

@section('content')
<!-- Page Hero -->
    <section class="cart-page-hero section-fade-up">
        <div class="container cart-page-hero-inner">
            <span class="cart-page-hero-eyebrow"><i class="fas fa-shopping-cart"></i> سلة المشتريات</span>
            <h1 class="cart-page-hero-title">راجع كورساتك المختارة</h1>
            <p class="cart-page-hero-desc">تأكد من اختياراتك ثم أكمل عملية الدفع بكل أمان وسهولة.</p>

            <nav class="cart-page-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <span>سلة المشتريات</span>
            </nav>
        </div>
    </section>

    <main class="container cart-page-main">
        <div id="toast-container"></div>

        <div class="checkout-steps section-fade-up" aria-label="خطوات الشراء">
            <div class="checkout-step is-active">
                <span class="checkout-step-num">1</span>
                <span class="checkout-step-label">السلة</span>
            </div>
            <span class="checkout-step-line"></span>
            <div class="checkout-step">
                <span class="checkout-step-num">2</span>
                <span class="checkout-step-label">الدفع</span>
            </div>
            <span class="checkout-step-line"></span>
            <div class="checkout-step">
                <span class="checkout-step-num">3</span>
                <span class="checkout-step-label">التأكيد</span>
            </div>
        </div>

        <div class="row g-4 g-lg-5 cart-layout">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <section class="cart-items-section section-fade-up">
                    <header class="cart-items-head">
                        <h2 class="cart-items-title">العناصر في السلة (<span id="cart-page-count" class="en-text">0</span>)</h2>
                        <button type="button" class="cart-clear-btn" id="clear-cart-btn" style="display: none;">
                            <i class="fas fa-trash-alt"></i> إفراغ السلة
                        </button>
                    </header>

                    <div id="cart-items-container" class="cart-items-list">
                        <div class="cart-empty d-none" id="empty-cart-msg">
                            <span class="cart-empty-icon"><i class="fas fa-shopping-cart"></i></span>
                            <h3>سلتك فارغة حالياً</h3>
                            <p>اكتشف كورساتنا المميزة وابدأ رحلة التعلم اليوم.</p>
                            <a href="{{ route('courses') }}" class="cart-empty-btn">تصفح الكورسات <i class="fas fa-arrow-left"></i></a>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <aside class="checkout-summary-card cart-summary-card section-fade-up">
                    <header class="checkout-card-head">
                        <span class="checkout-card-icon"><i class="fas fa-receipt"></i></span>
                        <h2 class="checkout-card-title">ملخص الطلب</h2>
                    </header>

                    <div class="checkout-card-body">
                        <div class="checkout-summary-totals cart-summary-totals-only">
                            <div class="checkout-summary-row">
                                <span>السعر الأصلي</span>
                                <span class="en-text" id="summary-old-total">$0</span>
                            </div>
                            <div class="checkout-summary-row is-discount">
                                <span>الخصومات</span>
                                <span class="en-text" id="summary-discount">-$0</span>
                            </div>
                            <div class="checkout-summary-row is-total">
                                <span>الإجمالي</span>
                                <strong class="en-text" id="summary-total">$0</strong>
                            </div>
                        </div>

                        <div class="cart-coupon-form">
                            <input type="text" class="checkout-input" placeholder="أدخل كود الخصم" id="coupon-input" autocomplete="off">
                            <button type="button" class="cart-coupon-btn" id="apply-coupon">تطبيق</button>
                        </div>
                        <p id="coupon-msg" class="cart-coupon-msg d-none" role="status"></p>

                        <a href="{{ route('checkout') }}" class="checkout-submit-btn cart-checkout-btn" id="checkout-btn">
                            إتمام الطلب <i class="fas fa-lock"></i>
                        </a>
                        <p class="checkout-submit-note"><i class="fas fa-shield-alt"></i> الدفع آمن ومشفّر 100%</p>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <!-- Footer -->
@endsection
