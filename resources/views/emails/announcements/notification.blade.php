@extends('emails.layout')

@section('content')
@php
    $theme = config('mail-theme');
@endphp

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('students.mail_greeting', ['name' => $recipient->first_name ?? $recipient->email]) }}
</p>

<p style="margin:0 0 12px;font-size:18px;font-weight:700;color:{{ $theme['title'] }};">
    {{ $announcement->title }}
</p>

<p style="margin:0 0 20px;font-size:15px;color:{{ $theme['text'] }};white-space:pre-line;">
    {{ $announcement->body }}
</p>

<p style="margin:0;font-size:13px;color:{{ $theme['text_muted'] }};">
    <a href="{{ $portalUrl }}" style="color:{{ $theme['primary'] }};font-weight:600;text-decoration:none;">
        {{ __('announcements.title') }}
    </a>
</p>
@endsection
