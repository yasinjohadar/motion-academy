@php
    $email = $siteSettings['site_email'];
    $phone = $siteSettings['site_phone'];
    $socialLinks = [
        ['url' => $siteSettings['facebook_url'] ?? '#', 'icon' => 'fab fa-facebook-f', 'label' => 'Facebook'],
        ['url' => $siteSettings['instagram_url'] ?? '#', 'icon' => 'fab fa-instagram', 'label' => 'Instagram'],
        ['url' => $siteSettings['linkedin_url'] ?? '#', 'icon' => 'fab fa-linkedin-in', 'label' => 'LinkedIn'],
        ['url' => $siteSettings['youtube_url'] ?? '#', 'icon' => 'fab fa-youtube', 'label' => 'YouTube'],
        ['url' => $siteSettings['telegram_url'] ?? '#', 'icon' => 'fab fa-telegram', 'label' => 'Telegram'],
    ];
@endphp
@php
    $socialLinks = array_filter($socialLinks, fn (array $social) => ! empty($social['url']) && $social['url'] !== '#');
@endphp
{{-- Nothing to show (no email, phone or social links in the site settings): no bar at all. --}}
@if (filled($email) || filled($phone) || $socialLinks !== [])
<div class="top-bar d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex gap-4 align-items-center">
            @if (filled($email))
                <a href="mailto:{{ $email }}" class="top-bar-item text-decoration-none"><i class="fas fa-envelope me-2"></i>{{ $email }}</a>
            @endif
            @if (filled($phone))
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="top-bar-item text-decoration-none"><i class="fas fa-phone me-2"></i>{{ $phone }}</a>
            @endif
        </div>
        <div class="d-flex gap-2 align-items-center">
            @foreach ($socialLinks as $social)
                @if (! empty($social['url']) && $social['url'] !== '#')
                    <a href="{{ $social['url'] }}" class="top-bar-social" target="_blank" rel="noopener noreferrer" aria-label="{{ $social['label'] }}"><i class="{{ $social['icon'] }}"></i></a>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endif
