@extends('emails.layout')

@section('content')
@php($theme = config('mail-theme'))
@php($starts = $event->starts_at->timezone(config('attendance.timezone'))->format('Y-m-d H:i'))

<p style="margin:0 0 16px;font-size:16px;">
    {{ __('events.mail_greeting', ['name' => $user->first_name ?? $user->email]) }}
</p>

<p style="margin:0 0 20px;font-size:15px;color:{{ $theme['text'] }};">
    {{ __('events.mail_confirmed_body', ['title' => $event->title, 'starts' => $starts]) }}
</p>

@include('emails.events.partials.details')

@if(!empty($checkInUrl))
    @include('emails.events.partials.check-in-qr')
@endif
@endsection
