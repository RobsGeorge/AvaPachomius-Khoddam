@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('church_applications.provisioned_admin_mail_intro', [
        'church' => $church->name,
        'email' => $application->contact_email,
    ]) }}
</p>

<p style="margin:0 0 16px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('church_applications.provisioned_admin_mail_slug', ['slug' => $church->slug]) }}
</p>

<p style="margin:0;">
    <a href="{{ $adminUrl }}" style="color:{{ $theme['link'] ?? '#4c1d95' }};">
        {{ __('church_applications.provisioned_admin_mail_cta') }}
    </a>
</p>
@endsection
