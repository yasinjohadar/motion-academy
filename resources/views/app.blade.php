<!DOCTYPE html>
<html lang="ar" dir="rtl" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="light" data-toggled="close">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ asset('assets/images/brand-logos/favicon.ico') }}" type="image/x-icon">

        <script src="{{ asset('assets/libs/choices.js/public/assets/scripts/choices.min.js') }}"></script>
        <script src="{{ asset('assets/js/main.js') }}"></script>

        <link id="style" href="{{ asset('assets/libs/bootstrap/css/bootstrap.rtl.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/css/styles.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/libs/node-waves/waves.min.css') }}" rel="stylesheet">
        <link href="{{ asset('assets/libs/simplebar/simplebar.min.css') }}" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/libs/flatpickr/flatpickr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/libs/@simonwep/pickr/themes/nano.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/libs/choices.js/public/assets/styles/choices.min.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/admin-pages.css') }}">

        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body>
        @include('partials.edu-switcher')

        @inertia

        {{-- The header off-canvas now lives in the React tree (Components/Admin/HeaderSidebar),
             because anything included here sits outside @inertia and can read no real data. --}}

        <div class="scrollToTop">
            <span class="arrow"><i class="las la-angle-double-up"></i></span>
        </div>
        <div id="responsive-overlay"></div>

        <script src="{{ asset('assets/libs/@popperjs/core/umd/popper.min.js') }}"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
        <script src="{{ asset('assets/js/sticky.js') }}"></script>
        <script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
        <script src="{{ asset('assets/js/simplebar.js') }}"></script>
        <script src="{{ asset('assets/libs/@simonwep/pickr/pickr.es5.min.js') }}"></script>
        <script src="{{ asset('assets/js/custom-switcher.min.js') }}"></script>
        <script src="{{ asset('assets/js/rtl-lock.js') }}"></script>
        <script>
            // Expose fullscreen helper used by the header button.
            window.openFullscreen = window.openFullscreen || function () {
                const elem = document.documentElement;
                if (!document.fullscreenElement) {
                    elem.requestFullscreen?.();
                } else {
                    document.exitFullscreen?.();
                }
            };
        </script>
    </body>
</html>
