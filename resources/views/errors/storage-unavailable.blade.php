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
    <meta name="robots" content="noindex" />
    <title>{{ $title }} — {{ __('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet" />
    @if($htmlDir === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet" />
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    @endif
    <link rel="stylesheet" href="{{ asset('css/khoddam-theme.css') }}?v=20260729-auth">
</head>
<body class="app-body theme-{{ $theme }} min-vh-100 d-flex align-items-center">
    <main class="container py-5" role="alert" aria-live="assertive">
        <div class="mx-auto text-center" style="max-width: 32rem;">
            <h1 class="h3 mb-3">{{ $title }}</h1>
            <p class="mb-2">{{ $message }}</p>
            <p class="text-muted mb-4">{{ $contact }}</p>
            <a href="{{ route('login') }}" class="btn btn-primary">{{ __('auth.back_to_login') }}</a>
        </div>
    </main>
</body>
</html>
