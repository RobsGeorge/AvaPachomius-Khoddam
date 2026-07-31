@php
    /** @var \App\Models\ChurchApplication $application */
    /** @var string $verifyUrl */
    /** @var string $statusUrl */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('church_applications.mail_subject') }}</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #222;">
    <p>{{ __('church_applications.mail_greeting', ['name' => $application->contact_name]) }}</p>
    <p>{{ __('church_applications.mail_intro', ['church' => $application->requested_name]) }}</p>
    <p>
        <a href="{{ $verifyUrl }}">{{ __('church_applications.mail_verify_cta') }}</a>
    </p>
    <p style="color: #666; font-size: 0.9rem;">{{ __('church_applications.mail_status_hint') }}</p>
    <p style="color: #666; font-size: 0.9rem;">
        <a href="{{ $statusUrl }}">{{ __('church_applications.mail_status_cta') }}</a>
    </p>
</body>
</html>
