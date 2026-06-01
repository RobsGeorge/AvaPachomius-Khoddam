<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_dir() }}">
<head>
    <meta charset="UTF-8">
</head>
<body>
    <h2>{{ __('auth.otp_email_title') }}</h2>
    <h2 style="color: #2e6da4;">{{ $otp }}</h2>
    <p>{{ __('auth.otp_valid_minutes') }}</p>
</body>
</html>
