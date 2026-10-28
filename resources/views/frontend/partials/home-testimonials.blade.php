{{--
    Home testimonials. Content comes from Testimonial rows; heading, count,
    order and layout come from TestimonialSettings (admin → آراء المتعلمين).
    The grid shows every picked testimonial; the slider pages through them.
--}}
@php
    $columnClass = match ((int) $testimonialSettings['columns']) {
        2 => 'col-md-6',
        4 => 'col-md-6 col-lg-3',
        default => 'col-md-6 col-lg-4',
    };
    $asSlider = $testimonialSettings['layout'] === 'slider';
@endphp
<section class="testimonials-section {{ $homeSections->zoneClasses('testimonials') }} section-fade-up">
    <div class="container">
        <div class="testimonials-header text-center">
            @if (filled($testimonialSettings['eyebrow']))
                <span class="testimonials-eyebrow">{{ $testimonialSettings['eyebrow'] }}</span>
            @endif
            <h2 class="testimonials-title">{{ $testimonialSettings['title'] }}</h2>
            @if (filled($testimonialSettings['subtitle']))
                <p class="testimonials-subtitle mx-auto">{{ $testimonialSettings['subtitle'] }}</p>
            @endif
        </div>

        @if ($asSlider)
            <div class="blog-swiper-wrap testimonials-swiper-wrap">
                <button class="blog-swiper-btn testimonials-swiper-prev" aria-label="السابق">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="swiper testimonials-swiper"
                     data-columns="{{ $testimonialSettings['columns'] }}"
                     data-autoplay="{{ $testimonialSettings['autoplay'] ? 'true' : 'false' }}">
                    <div class="swiper-wrapper">
                        @foreach ($testimonials as $testimonial)
                            <div class="swiper-slide h-auto">
                                @include('frontend.partials.testimonial-card')
                            </div>
                        @endforeach
                    </div>
                </div>
                <button class="blog-swiper-btn testimonials-swiper-next" aria-label="التالي">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
            <div class="swiper-pagination testimonials-swiper-pagination"></div>
        @else
            <div class="row g-4 testimonials-grid justify-content-center">
                @foreach ($testimonials as $testimonial)
                    <div class="{{ $columnClass }}">
                        @include('frontend.partials.testimonial-card')
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
