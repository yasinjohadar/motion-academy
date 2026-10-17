@extends('frontend.layouts.master')

@section('body_class', 'checkout-page')

@section('content')
<!-- Page Hero -->
    <header class="checkout-page-hero">
        <div class="container checkout-page-hero-inner">
            <span class="checkout-page-hero-eyebrow"><i class="fas fa-shield-alt"></i> دفع مشفّر وآمن</span>
            <h1 class="checkout-page-hero-title">إتمام الدفع الآمن</h1>
            <p class="checkout-page-hero-desc">خطوة واحدة تفصلك عن البدء في رحلة تعلمك — بياناتك محمية بالكامل.</p>
            <nav class="checkout-page-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <a href="{{ route('cart') }}">السلة</a>
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                <span class="current">الدفع</span>
            </nav>
        </div>
    </header>

    <main class="container checkout-main">
        <div id="toast-container"></div>

        <div class="checkout-steps section-fade-up" aria-label="خطوات الشراء">
            <div class="checkout-step is-done">
                <span class="checkout-step-num"><i class="fas fa-check"></i></span>
                <span class="checkout-step-label">السلة</span>
            </div>
            <span class="checkout-step-line"></span>
            <div class="checkout-step is-active">
                <span class="checkout-step-num">2</span>
                <span class="checkout-step-label">الدفع</span>
            </div>
            <span class="checkout-step-line"></span>
            <div class="checkout-step">
                <span class="checkout-step-num">3</span>
                <span class="checkout-step-label">التأكيد</span>
            </div>
        </div>

        <div class="row g-4 g-lg-5 checkout-layout">
            <!-- Order Summary (first on mobile) -->
            <div class="col-lg-5 order-1 order-lg-2">
                <aside class="checkout-summary-card section-fade-up">
                    <header class="checkout-card-head">
                        <span class="checkout-card-icon"><i class="fas fa-receipt"></i></span>
                        <h2 class="checkout-card-title">ملخص الطلب</h2>
                    </header>

                    <div class="checkout-card-body">
                        <div id="checkout-order-items" class="checkout-order-items">
                            <!-- populated by JS -->
                        </div>

                        <div class="checkout-summary-totals">
                            <div class="checkout-summary-row">
                                <span>المجموع</span>
                                <span class="en-text" id="checkout-subtotal">$0</span>
                            </div>
                            <div class="checkout-summary-row is-discount">
                                <span>الخصم</span>
                                <span class="en-text" id="checkout-discount">-$0</span>
                            </div>
                            <div class="checkout-summary-row is-total">
                                <span>الإجمالي</span>
                                <strong class="en-text" id="checkout-total">$0</strong>
                            </div>
                        </div>

                        <ul class="checkout-trust-list">
                            <li><i class="fas fa-lock"></i> SSL مشفّر</li>
                            <li><i class="fas fa-undo"></i> استرداد 30 يوم</li>
                            <li><i class="fas fa-infinity"></i> وصول مدى الحياة</li>
                        </ul>
                    </div>

                    <div id="order-success" class="checkout-success d-none">
                        <div class="checkout-success-icon"><i class="fas fa-check-circle"></i></div>
                        <h3>تم تأكيد طلبك بنجاح!</h3>
                        <p>سيتم إرسال تفاصيل الطلب على بريدك الإلكتروني.</p>
                        <a href="{{ route('courses') }}" class="checkout-success-btn">تصفح المزيد من الكورسات <i class="fas fa-arrow-left"></i></a>
                    </div>
                </aside>
            </div>

            <!-- Checkout Form -->
            <div class="col-lg-7 order-2 order-lg-1">
                <section class="checkout-card section-fade-up">
                    <header class="checkout-card-head">
                        <span class="checkout-card-icon"><i class="fas fa-user-circle"></i></span>
                        <h2 class="checkout-card-title">معلومات المشتري</h2>
                    </header>
                    <div class="checkout-card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="checkout-label" for="first-name">الاسم الأول</label>
                                <input type="text" class="checkout-input" id="first-name" placeholder="أحمد" autocomplete="given-name">
                                <div class="invalid-feedback">الاسم مطلوب</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="checkout-label" for="last-name">الاسم الأخير</label>
                                <input type="text" class="checkout-input" id="last-name" placeholder="سعيد" autocomplete="family-name">
                            </div>
                            <div class="col-12">
                                <label class="checkout-label" for="email">البريد الإلكتروني</label>
                                <input type="email" class="checkout-input" id="email" placeholder="ahmed@example.com" autocomplete="email">
                            </div>
                            <div class="col-12">
                                <label class="checkout-label" for="phone">رقم الهاتف</label>
                                <input type="tel" class="checkout-input en-text" id="phone" placeholder="+971 50 000 0000" dir="ltr" autocomplete="tel">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="checkout-card section-fade-up">
                    <header class="checkout-card-head">
                        <span class="checkout-card-icon"><i class="fas fa-credit-card"></i></span>
                        <h2 class="checkout-card-title">بيانات البطاقة</h2>
                    </header>
                    <div class="checkout-card-body">
                        <div class="credit-card-preview mb-4" id="card-preview">
                            <div class="card-inner">
                                <div class="card-front">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <i class="fas fa-graduation-cap fa-2x text-white opacity-50"></i>
                                        <div id="card-type-icon"><i class="fab fa-cc-visa fa-2x text-white opacity-75"></i></div>
                                    </div>
                                    <div class="card-chip mb-3">
                                        <i class="fas fa-microchip fa-2x text-white opacity-50"></i>
                                    </div>
                                    <div class="card-number-display en-text mb-3" id="card-number-display">•••• •••• •••• ••••</div>
                                    <div class="d-flex justify-content-between align-items-end">
                                        <div>
                                            <div class="card-label-sm">CARD HOLDER</div>
                                            <div class="card-holder-display en-text" id="card-holder-display">FULL NAME</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="card-label-sm">EXPIRES</div>
                                            <div class="card-exp-display en-text" id="card-exp-display">MM/YY</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-back">
                                    <div class="card-stripe"></div>
                                    <div class="card-cvv-strip">
                                        <span class="small text-secondary me-2">CVV</span>
                                        <div class="cvv-display en-text" id="card-cvv-display">•••</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="checkout-label" for="card-name">اسم حامل البطاقة</label>
                                <input type="text" class="checkout-input text-uppercase en-text" id="card-name" placeholder="AHMED SAID" maxlength="26" onkeyup="updateCardPreview()" autocomplete="cc-name">
                            </div>
                            <div class="col-12">
                                <label class="checkout-label" for="card-number">رقم البطاقة</label>
                                <input type="text" class="checkout-input en-text" id="card-number" placeholder="1234 5678 9012 3456" maxlength="19" oninput="formatCardNumber(this); updateCardPreview()" inputmode="numeric" autocomplete="cc-number">
                            </div>
                            <div class="col-6">
                                <label class="checkout-label" for="card-expiry">تاريخ الانتهاء</label>
                                <input type="text" class="checkout-input en-text" id="card-expiry" placeholder="MM/YY" maxlength="5" oninput="formatExpiry(this); updateCardPreview()" inputmode="numeric" autocomplete="cc-exp">
                            </div>
                            <div class="col-6">
                                <label class="checkout-label" for="card-cvv">رمز CVV</label>
                                <input type="password" class="checkout-input en-text" id="card-cvv" placeholder="•••" maxlength="4" onfocus="flipCard(true)" onblur="flipCard(false)" oninput="updateCardPreview()" inputmode="numeric" autocomplete="cc-csc">
                            </div>
                        </div>

                        <div class="checkout-payment-badges">
                            <span class="checkout-payment-label">نقبل:</span>
                            <i class="fab fa-cc-visa" title="Visa"></i>
                            <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                            <i class="fab fa-cc-paypal" title="PayPal"></i>
                            <i class="fab fa-cc-amex" title="Amex"></i>
                        </div>
                    </div>
                </section>

                <div class="checkout-submit-wrap section-fade-up">
                    <button type="button" class="checkout-submit-btn" id="submit-order" onclick="submitOrder()">
                        <i class="fas fa-lock"></i>
                        <span>تأكيد الدفع وإتمام الطلب</span>
                    </button>
                    <p class="checkout-submit-note"><i class="fas fa-shield-alt"></i> جميع بياناتك محمية ومشفرة 100%</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
@endsection
