{{--
    Search and social tags for every public page. Controllers pass `$seo` (a
    SeoPage); a page without one falls back to its @section('title') and the
    site-wide defaults set in the admin under "إعدادات الموقع ← SEO".
--}}
@php
    $seoPage = ($seo ?? null) instanceof \App\Services\Seo\SeoPage
        ? $seo
        : \App\Services\Seo\SeoPage::make()->title(trim($__env->yieldContent('title')) ?: null, exact: true);
    $seoHead = app(\App\Services\Seo\SiteSeo::class)->render($seoPage);
@endphp
<title>{{ $seoHead['title'] }}</title>
@foreach ($seoHead['meta'] as $tag)
<meta {{ $tag['attr'] }}="{{ $tag['key'] }}" content="{{ $tag['content'] }}">
@endforeach
@foreach ($seoHead['links'] as $link)
<link rel="{{ $link['rel'] }}" href="{{ $link['href'] }}">
@endforeach
<script type="application/ld+json">{!! json_encode($seoHead['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
