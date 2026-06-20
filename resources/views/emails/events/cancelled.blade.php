@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('events.mail_greeting', ['name' => $user->first_name ?? $user->email]) }}
</p>

<p style="margin:0 0 20px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('events.mail_cancelled_body', ['title' => $event->title]) }}
</p>

@include('emails.events.partials.details')

<p style="margin:0;font-size:13px;color:{{ $theme['text_muted'] }};">
    {{ __('events.mail_cancelled_footer') }}
</p>
@endsection
