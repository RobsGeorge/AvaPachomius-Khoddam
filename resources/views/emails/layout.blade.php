@php
    $theme = config('mail-theme');
    $emailTitle = $emailTitle ?? __('app.name');
    $headerSubtitle = $headerSubtitle ?? '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_dir() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $emailTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f0ff;font-family:'Cairo','Segoe UI',Tahoma,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           style="background:{{ $theme['bg_outer'] }};padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                       style="max-width:560px;background:{{ $theme['card_bg'] }};border-radius:16px;overflow:hidden;box-shadow:{{ $theme['card_shadow'] }};">
                    {{-- Header --}}
                    <tr>
                        <td style="padding:28px 32px;background:{{ $theme['header_bg'] }};color:{{ $theme['header_text'] }};">
                            <p style="margin:0 0 4px;font-size:12px;opacity:0.85;text-transform:uppercase;letter-spacing:0.06em;">
                                {{ __('app.tagline') }}
                            </p>
                            <h1 style="margin:0;font-size:22px;font-weight:700;line-height:1.3;">
                                {{ __('app.name') }}
                            </h1>
                            @if($headerSubtitle !== '')
                                <p style="margin:10px 0 0;font-size:14px;opacity:0.92;line-height:1.5;">
                                    {{ $headerSubtitle }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px;color:{{ $theme['text'] }};line-height:1.7;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 32px;background:{{ $theme['footer_bg'] }};border-top:1px solid {{ $theme['border'] }};">
                            <p style="margin:0 0 8px;font-size:12px;color:{{ $theme['text_muted'] }};text-align:center;">
                                {{ __('mail.footer_notice') }}
                            </p>
                            <p style="margin:0;font-size:11px;color:{{ $theme['text_light'] }};text-align:center;">
                                &copy; {{ date('Y') }} {{ __('app.name') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
