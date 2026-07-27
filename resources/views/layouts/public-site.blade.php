<!DOCTYPE html>
@php
    $branding = $branding ?? \App\Support\PublicSite\ChurchBranding::fromSettings($church->settings ?? []);
    $logoUrl = \App\Support\PublicSite\ChurchBranding::logoUrl($branding);
    $psCss = \App\Support\PublicSite\ChurchBranding::publicCss($branding);
    $isRtl = app()->getLocale() === 'ar';
    $preview = $preview ?? false;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if($preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <title>{{ $church->name }} — {{ __('public_site.homepage_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap{{ $isRtl ? '.rtl' : '' }}.min.css" rel="stylesheet">
    <link href="{{ asset('css/public-site.css') }}" rel="stylesheet">
    <style>
        body.public-site { {!! $psCss !!} }
    </style>
</head>
<body class="public-site">
    <header class="ps-header">
        <div class="container ps-header-inner">
            <div class="d-flex align-items-center gap-3">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $church->name }}" class="ps-logo">
                @endif
                <div>
                    <div class="ps-church-name">{{ $church->name }}</div>
                    @if($preview)
                        <span class="badge bg-warning text-dark">{{ __('public_site.preview_badge') }}</span>
                    @endif
                </div>
            </div>
            <nav class="ps-header-nav">
                <a href="{{ route('public.church.profile') }}">{{ __('public_site.about') }}</a>
                <a href="{{ route('login') }}">{{ __('public_site.login') }}</a>
                @foreach(config('translation.supported_locales', ['ar', 'en']) as $localeCode)
                    <a href="{{ route('locale.switch', $localeCode) }}" @class(['fw-bold' => app()->getLocale() === $localeCode])>
                        {{ config('translation.locale_labels.'.$localeCode, $localeCode) }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="ps-footer">
        <div class="container text-center">
            <small class="text-muted">&copy; {{ $church->name }}</small>
        </div>
    </footer>
</body>
</html>
