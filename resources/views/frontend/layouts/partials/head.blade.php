<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="courses-search-url" content="{{ route('courses.search') }}">
<meta name="courses-url" content="{{ route('courses') }}">
<meta name="search-suggest-url" content="{{ route('search.suggest') }}">
@include('frontend.layouts.partials.seo')
@hasSection('meta')
    @yield('meta')
@endif
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
{{-- Course categories are given Remix icons (ri-*) in the admin. --}}
<link rel="stylesheet" href="{{ asset('assets/icon-fonts/RemixIcons/fonts/remixicon.css') }}">
@stack('vendor-styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;600;700;800&family=Cairo:wght@300;400;600;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ $fa }}/css/style.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-hero.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-search.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-course.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-cards.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-catalog.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-nav.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-student.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-learn.css">
<link rel="stylesheet" href="{{ $fa }}/css/brand-story.css">
<link rel="icon" type="image/png" href="{{ $fa }}/images/favicon.png">
<link rel="apple-touch-icon" href="{{ $fa }}/images/logo-mark.png">
<meta name="theme-color" content="#02ad8f">
@stack('styles')
