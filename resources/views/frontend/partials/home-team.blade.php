{{--
    Home team section. Members come from TeamMember rows; heading, count,
    order, layout and the call to action come from TeamSettings
    (admin → فريق الخبراء).
--}}
@php
    $columnClass = match ((int) $teamSettings['columns']) {
        2 => 'col-md-6',
        3 => 'col-md-6 col-lg-4',
        default => 'col-md-6 col-lg-3',
    };
@endphp
<section class="team-section {{ $homeSections->zoneClasses('team') }} section-fade-up">
    <div class="container">
        <div class="team-header text-center">
            @if (filled($teamSettings['eyebrow']))
                <span class="team-eyebrow">{{ $teamSettings['eyebrow'] }}</span>
            @endif
            <h2 class="team-title">{{ $teamSettings['title'] }}</h2>
            @if (filled($teamSettings['subtitle']))
                <p class="team-subtitle mx-auto">{{ $teamSettings['subtitle'] }}</p>
            @endif
        </div>

        @if ($teamSettings['layout'] === 'slider')
            <div class="team-swiper-wrap">
                <button class="team-swiper-btn team-swiper-prev" aria-label="السابق">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <div class="swiper team-swiper"
                     data-columns="{{ $teamSettings['columns'] }}"
                     data-autoplay="{{ $teamSettings['autoplay'] ? 'true' : 'false' }}">
                    <div class="swiper-wrapper">
                        @foreach ($teamMembers as $member)
                            <div class="swiper-slide">
                                @include('frontend.partials.team-card')
                            </div>
                        @endforeach
                    </div>
                </div>

                <button class="team-swiper-btn team-swiper-next" aria-label="التالي">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>

            <div class="swiper-pagination team-swiper-pagination"></div>
        @else
            <div class="row g-4 justify-content-center">
                @foreach ($teamMembers as $member)
                    <div class="{{ $columnClass }}">
                        @include('frontend.partials.team-card')
                    </div>
                @endforeach
            </div>
        @endif

        @if (filled($teamSettings['cta_label']) && filled($teamSettings['cta_url']))
            <div class="text-center team-footer-cta">
                <a href="{{ $teamSettings['cta_url'] }}" class="btn team-view-all">
                    {{ $teamSettings['cta_label'] }} <i class="fas fa-arrow-left ms-2"></i>
                </a>
            </div>
        @endif
    </div>
</section>
