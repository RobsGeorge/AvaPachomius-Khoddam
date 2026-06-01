<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ locale_dir() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('password.reset_email_subject') }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f0ff;font-family:'Segoe UI',Tahoma,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:linear-gradient(135deg,#f5f0ff 0%,#ede4ff 45%,#e8efff 100%);padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 12px 32px rgba(91,33,182,0.12);">
                    <tr>
                        <td style="padding:28px 32px;background:linear-gradient(135deg,#5b21b6,#7c3aed);color:#ffffff;">
                            <h1 style="margin:0;font-size:22px;font-weight:700;">{{ __('app.name') }}</h1>
                            <p style="margin:8px 0 0;font-size:14px;opacity:0.92;">{{ __('password.reset_email_subject') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;color:#334155;line-height:1.7;">
                            <p style="margin:0 0 16px;font-size:16px;">
                                {{ __('password.reset_email_greeting', ['name' => $user->first_name ?? __('dashboard.user_fallback')]) }}
                            </p>
                            <p style="margin:0 0 24px;font-size:15px;">
                                {{ __('password.reset_email_body') }}
                            </p>
                            <p style="margin:0 0 28px;text-align:center;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block;padding:14px 28px;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;font-size:15px;">
                                    {{ __('password.reset_email_button') }}
                                </a>
                            </p>
                            <p style="margin:0 0 12px;font-size:13px;color:#64748b;">
                                {{ __('password.reset_email_expiry', ['minutes' => $expireMinutes]) }}
                            </p>
                            <p style="margin:0 0 12px;font-size:13px;color:#64748b;">
                                {{ __('password.reset_email_ignore') }}
                            </p>
                            <p style="margin:0;font-size:13px;color:#64748b;">
                                {{ __('auth.check_spam') }}
                            </p>
                            <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;word-break:break-all;">
                                {{ __('password.reset_email_link_fallback') }}<br>
                                <a href="{{ $resetUrl }}" style="color:#6d28d9;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
