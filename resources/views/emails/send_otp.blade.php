@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))

@if(! empty($user?->first_name))
    <p style="margin:0 0 16px;font-size:16px;">
        {{ __('auth.otp_email_greeting', ['name' => $user->first_name]) }}
    </p>
@endif

<p style="margin:0 0 24px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('auth.otp_email_body') }}
</p>

{{-- OTP code highlight panel --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
    <tr>
        <td align="center"
            style="padding:28px 20px;background:{{ $theme['code_panel_bg'] }};border:2px solid {{ $theme['code_panel_border'] }};border-radius:14px;">
            <p style="margin:0 0 12px;font-size:13px;font-weight:600;color:{{ $theme['primary_dark'] }};text-transform:uppercase;letter-spacing:0.04em;">
                {{ __('auth.otp_email_title') }}
            </p>
            <p style="margin:0;font-size:36px;font-weight:800;letter-spacing:0.35em;color:{{ $theme['title'] }};font-family:'Courier New',Courier,monospace;">
                {{ $otp }}
            </p>
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('auth.otp_valid_minutes') }}
</p>

<p style="margin:0;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('auth.check_spam') }}
</p>
@endsection
