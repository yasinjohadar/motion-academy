<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    @include('frontend.layouts.partials.head')
</head>
<body @hasSection('body_class') class="@yield('body_class')" @endif>
    @include('frontend.partials.impersonation-banner')
    @include('frontend.layouts.partials.topbar')
    @include('frontend.layouts.navbar')

    <div id="toast-container"></div>
    @yield('content')

    @unless (View::hasSection('hide_footer'))
        @include('frontend.layouts.footer')
    @endunless
    @include('frontend.layouts.partials.scripts')
</body>
</html>
