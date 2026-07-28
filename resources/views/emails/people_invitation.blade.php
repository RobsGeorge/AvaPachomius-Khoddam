@php
    /** @var \App\Models\User $user */
    /** @var string $otp */
    /** @var string $claimUrl */
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('people_onboarding.invite_email_subject') }}</title>
</head>
<body style="font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #222;">
    <p>{{ __('people_onboarding.invite_email_greeting', ['name' => $user->displayName()]) }}</p>
    <p>{{ __('people_onboarding.invite_email_intro') }}</p>
    <p>
        <strong>{{ __('people_onboarding.invite_email_otp_label') }}:</strong>
        <span style="font-size: 1.4rem; letter-spacing: 0.15em;">{{ $otp }}</span>
    </p>
    <p>
        <a href="{{ $claimUrl }}">{{ __('people_onboarding.invite_email_cta') }}</a>
    </p>
    <p style="color: #666; font-size: 0.9rem;">{{ __('people_onboarding.invite_email_expiry') }}</p>
</body>
</html>
