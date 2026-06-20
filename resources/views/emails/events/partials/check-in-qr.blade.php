@php($theme = config('mail-theme'))

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0 0;">
    <tr>
        <td align="center" style="padding:20px;background:{{ $theme['footer_bg'] }};border:1px solid {{ $theme['border'] }};border-radius:12px;">
            <p style="margin:0 0 12px;font-size:13px;font-weight:600;color:{{ $theme['text'] }};">
                {{ __('events.mail_check_in_qr') }}
            </p>
            <div style="display:inline-block;padding:12px;background:#ffffff;border-radius:8px;">
                {!! QrCode::size(180)->margin(1)->generate($checkInUrl) !!}
            </div>
            <p style="margin:12px 0 0;font-size:12px;color:{{ $theme['text_muted'] }};">
                {{ __('events.mail_check_in_qr_hint') }}
            </p>
        </td>
    </tr>
</table>

<p style="margin:16px 0 0;font-size:12px;color:{{ $theme['text_light'] }};word-break:break-all;">
    {{ __('events.mail_check_in_link_fallback') }}<br>
    <a href="{{ $checkInUrl }}" style="color:{{ $theme['primary_dark'] }};">{{ $checkInUrl }}</a>
</p>
