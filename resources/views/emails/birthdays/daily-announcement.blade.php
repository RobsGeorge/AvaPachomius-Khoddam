@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px;font-size:16px;">
    {{ __('students.mail_greeting', ['name' => $recipient->first_name ?? $recipient->email]) }}
</p>

<p style="margin:0 0 20px;font-size:15px;">
    {{ __('students.mail_intro_daily', ['course' => $course->title, 'date' => $dateLabel]) }}
</p>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       style="border-collapse:collapse;margin:0 0 20px;font-size:14px;">
    <thead>
        <tr>
            <th align="left" style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                {{ __('pages.student') }}
            </th>
            <th align="left" style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                {{ __('pages.birth_date') }}
            </th>
            <th align="left" style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                {{ __('pages.email') }}
            </th>
            <th align="left" style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                {{ __('pages.phone') }}
            </th>
        </tr>
    </thead>
    <tbody>
        @foreach($birthdayStudents as $student)
            <tr>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">
                    @if($whatsappUrl = $student->whatsappUrl(__('students.whatsapp_birthday_message')))
                        <a href="{{ $whatsappUrl }}"
                           style="display:inline-block;margin-inline-end:8px;padding:6px 10px;background:#25D366;color:#ffffff;text-decoration:none;border-radius:6px;font-size:12px;font-weight:600;">
                            {{ __('students.whatsapp') }}
                        </a>
                    @endif
                    {{ $student->displayName() }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">
                    {{ $student->date_of_birth?->format('d/m') }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">
                    {{ $student->email ?? '—' }}
                </td>
                <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;">
                    {{ $student->formattedMobile() ?? '—' }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin:0;font-size:13px;color:#64748b;">
    {{ __('students.mail_footer_before') }}
    <a href="{{ $rosterUrl }}" style="color:#7c3aed;font-weight:600;text-decoration:none;">
        {{ __('students.mail_footer_link') }}
    </a>{{ __('students.mail_footer_after') }}
</p>
@endsection
