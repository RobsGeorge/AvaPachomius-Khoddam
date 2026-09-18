@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('church_applications.mail_greeting', ['name' => $application->contact_name]) }}
</p>

<p style="margin:0 0 16px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('church_applications.founder_ready_mail_intro', ['church' => $church->name]) }}
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 28px;">
    <tr>
        <td align="center"
            style="padding:24px 20px;background:{{ $theme['gold_panel_bg'] }};border:2px solid {{ $theme['gold_panel_border'] }};border-radius:14px;">
            <a href="{{ $loginUrl }}"
               style="display:inline-block;padding:14px 32px;background:{{ $theme['gold_gradient'] }};color:#1a202c;text-decoration:none;border-radius:10px;font-weight:800;font-size:15px;">
                {{ __('church_applications.founder_ready_mail_cta') }}
            </a>
        </td>
    </tr>
</table>

<p style="margin:0;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ $churchUrl }}
</p>
@endsection
