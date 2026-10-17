@extends('frontend.layouts.master')

@section('body_class', 'courses-page ct-page')

@php
    $selectedCategories = $filters['categories'];
    $selectedLevels = $filters['levels'];
    $priceCeiling = $filters['price_max'] !== null ? (int) $filters['price_max'] : $maxPrice;
    $sortLabels = [
        'popular' => ['المميزة أولاً', 'fas fa-star'],
        'newest' => ['الأحدث', 'fas fa-bolt'],
        'price-asc' => ['السعر: الأقل', 'fas fa-arrow-down-short-wide'],
        'price-desc' => ['السعر: الأعلى', 'fas fa-arrow-up-wide-short'],
    ];
@endphp

@section('content')
    {{-- ================= Hero ================= --}}
    <section class="ct-hero">
        <div class="ct-hero-bg" aria-hidden="true"><span></span><span></span><i></i><i></i><i></i></div>
        <div class="container position-relative">
            <nav class="ct-breadcrumb" aria-label="breadcrumb">
                <a href="{{ route('home') }}">الرئيسية</a>
                <i class="fas fa-chevron-left"></i>
                <span>الدورات</span>
            </nav>

            <div class="ct-hero-grid">
                <div>
                    <span class="ct-eyebrow"><i class="fas fa-photo-film"></i> مكتبة الدورات</span>
                    <h1 class="ct-title">اختر مشهدك <span>الأول</span></h1>
                    <p class="ct-desc">دورات عملية في التحريك وصناعة المحتوى البصري — ابدأ بدرس مجاني من أي دورة.</p>

                    <label class="ct-search" for="ct-search-input">
                        <i class="fas fa-search"></i>
                        <input type="search" id="ct-search-input" name="search" value="{{ $filters['search'] }}" placeholder="ابحث باسم الدورة أو المهارة..." autocomplete="off">
                        <span class="ct-search-clear" role="button" tabindex="0" aria-label="مسح البحث" @if ($filters['search'] === '') hidden @endif><i class="fas fa-times"></i></span>
                    </label>
                </div>

                <ul class="ct-stats" aria-label="أرقام المكتبة">
                    <li><strong class="en-text">{{ $stats['total'] }}</strong><span>دورة</span></li>
                    <li><strong class="en-text">{{ $stats['lessons'] }}</strong><span>درس</span></li>
                    <li><strong class="en-text">{{ $stats['hours'] }}</strong><span>ساعة محتوى</span></li>
                    <li><strong class="en-text">{{ $stats['free'] }}</strong><span>دورة مجانية</span></li>
                </ul>
            </div>
        </div>
    </section>

    {{-- ================= Category tracks ================= --}}
    <div class="ct-tracks-wrap">
        <div class="container">
            <div class="ct-tracks" role="group" aria-label="المجالات">
                <button type="button" class="ct-track {{ $selectedCategories === [] ? 'is-active' : '' }}" data-category="" style="--c: #1a83c1;">
                    <span class="ct-track-icon"><i class="fas fa-layer-group"></i></span>
                    <span class="ct-track-name">كل المجالات</span>
                    <span class="ct-track-count en-text">{{ $stats['total'] }}</span>
                </button>
                @foreach ($categories as $category)
                    <button type="button" class="ct-track {{ in_array($category->slug, $selectedCategories, true) ? 'is-active' : '' }}" data-category="{{ $category->slug }}" style="--c: {{ $category->color ?? '#02ad8f' }};">
                        <span class="ct-track-icon"><i class="{{ $category->publicIcon() }}"></i></span>
                        <span class="ct-track-name">{{ $category->name }}</span>
                        <span class="ct-track-count en-text">{{ $category->courses_count }}</span>
                        <span class="ct-track-bar" aria-hidden="true"></span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ================= Catalogue ================= --}}
    <main class="container ct-main" id="ct-catalog"
          data-search-url="{{ route('courses.search') }}"
          data-max-price="{{ $maxPrice }}">
        <div class="row g-4">
            {{-- Inspector panel --}}
            <aside class="col-lg-3">
                <button class="ct-filter-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#ctInspector" aria-expanded="false">
                    <i class="fas fa-sliders-h"></i> الفلاتر <span class="ct-filter-badge en-text" data-active-count hidden>0</span>
                </button>

                <div class="collapse d-lg-block" id="ctInspector">
                    <div class="ct-inspector">
                        <div class="ct-inspector-head">
                            <span class="ct-inspector-title"><i class="fas fa-sliders-h"></i> الخصائص</span>
                            <button type="button" class="ct-reset" data-reset><i class="fas fa-rotate-left"></i> إعادة ضبط</button>
                        </div>

                        <div class="ct-prop">
                            <span class="ct-prop-label"><i class="fas fa-tag"></i> نوع الدورة</span>
                            <div class="ct-segment" data-segment="pricing">
                                @foreach (['' => 'الكل', 'free' => 'مجانية', 'sale' => 'عليها خصم', 'paid' => 'مدفوعة'] as $value => $label)
                                    <button type="button" class="{{ $filters['pricing'] === $value ? 'is-active' : '' }}" data-value="{{ $value }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="ct-prop">
                            <span class="ct-prop-label"><i class="fas fa-signal"></i> المستوى</span>
                            <div class="ct-levels">
                                @foreach ($levels as $index => $level)
                                    <button type="button" class="ct-level {{ in_array($level['value'], $selectedLevels, true) ? 'is-active' : '' }}" data-level="{{ $level['value'] }}">
                                        <span class="ct-level-bars" aria-hidden="true">
                                            @for ($bar = 0; $bar < 3; $bar++)
                                                <i class="{{ $bar <= $index ? 'on' : '' }}"></i>
                                            @endfor
                                        </span>
                                        {{ $level['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="ct-prop">
                            <span class="ct-prop-label"><i class="fas fa-coins"></i> السعر الأقصى</span>
                            <div class="ct-range">
                                <output class="ct-range-bubble en-text" data-price-out>${{ $priceCeiling }}</output>
                                <input type="range" min="0" max="{{ $maxPrice }}" step="5" value="{{ $priceCeiling }}" data-price aria-label="السعر الأقصى">
                                <div class="ct-range-scale en-text"><span>$0</span><span>${{ (int) round($maxPrice / 2) }}</span><span>${{ $maxPrice }}</span></div>
                            </div>
                        </div>

                        <div class="ct-inspector-foot">
                            <i class="fas fa-circle-info"></i>
                            الدورات لكل المستويات تظهر مع أي مستوى تختاره.
                        </div>
                    </div>
                </div>
            </aside>

            <div class="col-lg-9">
                {{-- Toolbar --}}
                <div class="ct-toolbar">
                    <div class="ct-count">
                        <span class="ct-count-num en-text" data-count>{{ $courses->total() }}</span>
                        <span>دورة مطابقة</span>
                    </div>

                    <div class="ct-toolbar-actions">
                        <div class="ct-sort" data-sort-menu>
                            <button type="button" class="ct-sort-btn" data-sort-toggle aria-haspopup="listbox" aria-expanded="false">
                                <i class="{{ $sortLabels[$filters['sort']][1] }}" data-sort-icon></i>
                                <span data-sort-label>{{ $sortLabels[$filters['sort']][0] }}</span>
                                <i class="fas fa-chevron-down ct-sort-caret"></i>
                            </button>
                            <ul class="ct-sort-list" role="listbox" hidden>
                                @foreach ($sortLabels as $value => [$label, $icon])
                                    <li><button type="button" role="option" class="{{ $filters['sort'] === $value ? 'is-active' : '' }}" data-sort="{{ $value }}" data-icon="{{ $icon }}"><i class="{{ $icon }}"></i> {{ $label }}</button></li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="ct-view" role="group" aria-label="طريقة العرض">
                            <button type="button" class="is-active" data-view="grid" aria-label="عرض شبكي"><i class="fas fa-grip"></i></button>
                            <button type="button" data-view="list" aria-label="عرض قائمة"><i class="fas fa-list"></i></button>
                        </div>
                    </div>
                </div>

                {{-- Active filters as removable chips --}}
                <div class="ct-chips" data-chips hidden></div>

                <div class="ct-results">
                    <div class="ct-loader" data-loader hidden><span></span><span></span><span></span></div>
                    <div class="row g-4" id="all-courses-container" data-results>
                        @forelse ($courses as $course)
                            @include('frontend.partials.course-card-grid', ['course' => $course])
                        @empty
                            @include('frontend.partials.courses-empty')
                        @endforelse
                    </div>
                </div>

                <div id="courses-pagination-wrap" data-pagination>
                    @include('frontend.partials.courses-pagination', ['courses' => $courses])
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
<script src="{{ $fa }}/js/catalog.js"></script>
@endpush
