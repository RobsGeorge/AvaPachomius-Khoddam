@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px;font-size:16px;">
    {{ $notification->title }}
</p>

<p style="margin:0 0 16px;font-size:15px;">
    {{ $notification->body }}
</p>

@if(! empty($actionUrl))
    <p style="margin:0;">
        <a href="{{ $actionUrl }}"
           style="display:inline-block;padding:12px 24px;background:#7c3aed;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
            {{ __('notifications.hub_title') }}
        </a>
    </p>
@endif
@endsection
