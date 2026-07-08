@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('students.mail_greeting', ['name' => $recipient->first_name ?? $recipient->email]) }}
</p>

<p style="margin:0 0 20px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('students.mail_intro', ['course' => $course->title, 'month' => $monthLabel]) }}
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;margin:0 0 20px;font-size:14px;">
    <thead>
        <tr>
            <th align="left" style="padding:10px 12px;background:{{ $theme['footer_bg'] }};border-bottom:1px solid {{ $theme['border'] }};">
                {{ __('pages.student') }}
            </th>
            <th align="left" style="padding:10px 12px;background:{{ $theme['footer_bg'] }};border-bottom:1px solid {{ $theme['border'] }};">
                {{ __('pages.birth_date') }}
            </th>
            <th align="left" style="padding:10px 12px;background:{{ $theme['footer_bg'] }};border-bottom:1px solid {{ $theme['border'] }};">
                {{ __('pages.email') }}
            </th>
            <th align="left" style="padding:10px 12px;background:{{ $theme['footer_bg'] }};border-bottom:1px solid {{ $theme['border'] }};">
                {{ __('pages.phone') }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($birthdayStudents as $student)
            @php
                $whatsappMessage = __('students.whatsapp_birthday_message', ['name' => $student->displayName()]);
                $whatsappUrl = $student->whatsappUrl($whatsappMessage);
            @endphp
            <tr>
                <td style="padding:10px 12px;border-bottom:1px solid {{ $theme['border'] }};">
                    @if($whatsappUrl)
                        <a href="{{ $whatsappUrl }}"
                           style="display:inline-block;margin-inline-end:8px;padding:6px 10px;background:#25D366;color:#ffffff;text-decoration:none;border-radius:6px;font-size:12px;font-weight:600;">
                            {{ __('students.whatsapp') }}
                        </a>
                    @endif
                    {{ $student->displayName() }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid {{ $theme['border'] }};">
                    {{ $student->date_of_birth?->format('d/m') }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid {{ $theme['border'] }};">
                    {{ $student->email ?? '—' }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid {{ $theme['border'] }};">
                    {{ $student->formattedMobile() ?? '—' }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin:0;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('students.mail_footer_before') }}
    <a href="{{ $rosterUrl }}" style="color:{{ $theme['primary'] }};font-weight:600;text-decoration:none;">
        {{ __('students.mail_footer_link') }}
    </a>{{ __('students.mail_footer_after') }}
</p>
@endsection
