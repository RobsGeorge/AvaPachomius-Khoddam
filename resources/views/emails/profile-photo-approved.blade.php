@extends('emails.layout')

@section('content')
@if(! empty($user->first_name))
    <p style="margin:0 0 16px;font-size:16px;">
        {{ __('profile_photos.approval_email_greeting', ['name' => $user->first_name]) }}
    </p>
@endif

<p style="margin:0 0 16px;font-size:15px;">
    {{ __('profile_photos.approval_email_body') }}
</p>

<p style="margin:0 0 24px;font-size:15px;">
    {{ __('profile_photos.approval_email_action') }}
</p>

<p style="margin:0;">
    <a href="{{ $dashboardUrl }}"
       style="display:inline-block;padding:12px 24px;background:#114b4f;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
        {{ __('profile_photos.approval_email_button') }}
    </a>
</p>
@endsection
