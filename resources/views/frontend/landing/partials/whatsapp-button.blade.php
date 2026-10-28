{{-- The large animated WhatsApp button of the course landing page. --}}
<a href="{{ $whatsapp }}" class="wa {{ $class ?? '' }}" target="_blank" rel="noopener noreferrer">
    @include('frontend.landing.partials.whatsapp-icon')
    <span>
        <b>تواصل معنا على واتساب</b>
        <small>اسألنا عن الدورة — نرد عليك بسرعة</small>
    </span>
    <i aria-hidden="true">‹</i>
</a>
