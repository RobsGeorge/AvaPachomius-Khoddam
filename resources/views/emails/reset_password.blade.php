@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('password.reset_email_greeting', ['name' => $user->first_name ?? __('dashboard.user_fallback')]) }}
</p>

<p style="margin:0 0 24px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('password.reset_email_body') }}
</p>

{{-- Gold highlight panel for reset action --}}
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
    <tr>
        <td align="center"
            style="padding:24px 20px;background:{{ $theme['gold_panel_bg'] }};border:2px solid {{ $theme['gold_panel_border'] }};border-radius:14px;">
            <p style="margin:0 0 16px;font-size:13px;font-weight:600;color:{{ $theme['gold_dark'] }};text-transform:uppercase;letter-spacing:0.04em;">
                {{ __('mail.reset_action_label') }}
            </p>
            <a href="{{ $resetUrl }}"
               style="display:inline-block;padding:14px 32px;background:{{ $theme['gold_gradient'] }};color:#1a202c;text-decoration:none;border-radius:10px;font-weight:800;font-size:15px;box-shadow:0 4px 14px rgba(212,175,55,0.35);">
                {{ __('password.reset_email_button') }}
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('password.reset_email_expiry', ['minutes' => $expireMinutes]) }}
</p>

<p style="margin:0 0 12px;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('password.reset_email_ignore') }}
</p>

<p style="margin:0 0 0;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('auth.check_spam') }}
</p>

<hr style="margin:24px 0;border:none;border-top:1px solid {{ $theme['border'] }};">

<p style="margin:0;font-size:12px;color:{{ $theme['text_light'] }};word-break:break-all;">
    {{ __('password.reset_email_link_fallback') }}<br>
    <a href="{{ $resetUrl }}" style="color:{{ $theme['primary_dark'] }};">{{ $resetUrl }}</a>
</p>
@endsection
