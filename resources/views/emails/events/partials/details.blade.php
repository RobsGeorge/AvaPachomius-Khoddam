@php($theme = config('mail-theme'))
@php($starts = $event->starts_at->timezone(config('attendance.timezone'))->format('Y-m-d H:i'))

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
    <tr>
        <td style="padding:16px 18px;background:{{ $theme['footer_bg'] }};border:1px solid {{ $theme['border'] }};border-radius:12px;">
            <p style="margin:0 0 8px;font-size:13px;color:{{ $theme['text_muted'] }};">{{ __('events.event') }}</p>
            <p style="margin:0 0 12px;font-size:17px;font-weight:700;color:{{ $theme['text'] }};">{{ $event->title }}</p>
            @if($event->location)
                <p style="margin:0 0 6px;font-size:14px;color:{{ $theme['text'] }};">
                    <strong>{{ __('events.location') }}:</strong> {{ $event->location }}
                </p>
            @endif
            <p style="margin:0;font-size:14px;color:{{ $theme['text'] }};">
                <strong>{{ __('events.starts') }}:</strong> {{ $starts }}
            </p>
        </td>
    </tr>
</table>
