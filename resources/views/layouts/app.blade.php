<!DOCTYPE html>
@php
    $htmlDir = locale_dir();
    $htmlLang = str_replace('_', '-', app()->getLocale());
    $theme = request()->cookie('theme', 'light');
@endphp
<html lang="{{ $htmlLang }}" dir="{{ $htmlDir }}" data-bs-theme="{{ $theme }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('app.name'))</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet" />

    @if($htmlDir === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet" />
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    @endif

    <link rel="stylesheet" href="{{ asset('css/khoddam-theme.css') }}?v=20260714b">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}?v=20260714" media="print">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" referrerpolicy="no-referrer">

    @if(!empty($courseBrandingCss))
        <style>{!! $courseBrandingCss !!}</style>
    @endif

    @stack('styles')
</head>

<body class="app-body theme-{{ $theme }} min-vh-100 d-flex flex-column"
      x-data="{ navOpen: false, navScrollY: 0 }"
      x-effect="if (navOpen && window.matchMedia('(max-width: 767.98px)').matches) {
          navScrollY = window.scrollY;
          $el.classList.add('mobile-nav-open');
          $el.style.top = `-${navScrollY}px`;
      } else {
          $el.classList.remove('mobile-nav-open');
          $el.style.top = '';
          if (navScrollY) window.scrollTo(0, navScrollY);
      }">
    <a class="skip-link visually-hidden-focusable" href="#app-main">{{ __('a11y.skip_to_content') }}</a>
    {{-- Polite live region: JS mirrors toast/flash messages here so screen readers announce them. --}}
    <div id="khoddam-live-region" class="visually-hidden" aria-live="polite" role="status"></div>
    <div class="app-shell d-flex flex-column flex-grow-1">
        @include('layouts.navigation')

        @include('layouts.partials.profile-photo-banner')
        @include('layouts.partials.application-review-banner')
        @include('layouts.partials.announcement-banners')

        @include('layouts.impersonation-banner')
        @include('layouts.role-preview-banner')

        <main id="app-main" tabindex="-1" class="app-main flex-grow-1">
            @include('layouts.partials.flash-toast')
            @yield('content')
        </main>
    </div>

    @include('layouts.partials.student-onboarding-wizard')

    @stack('modals')
    @include('students.partials.student-photo-modal')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.all.min.js"></script>
    {{-- Pin Alpine exactly: floating @3.x.x is a supply-chain risk (auto-pulls any new 3.x). --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.12/dist/cdn.min.js"></script>
    <script src="{{ asset('js/khoddam-ui.js') }}?v=20260714b"></script>
    @stack('scripts')
</body>
</html>
