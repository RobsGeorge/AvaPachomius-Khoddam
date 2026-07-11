@extends('emails.layout')

@section('content')
@if(! empty($user->first_name))
    <p style="margin:0 0 16px;font-size:16px;">
        {{ __('profile_photos.rejection_email_greeting', ['name' => $user->first_name]) }}
    </p>
@endif

<p style="margin:0 0 16px;font-size:15px;">
    {{ __('profile_photos.rejection_email_body') }}
</p>

@if(! empty($user->profile_photo_rejection_note))
    <p style="margin:0 0 16px;font-size:14px;color:#64748b;padding:12px 16px;background:linear-gradient(135deg, #f5f0ff 0%, #ede4ff 60%, #fef9e7 100%);border-radius:10px;">
        <strong>{{ __('profile_photos.rejection_note') }}:</strong>
        {{ $user->profile_photo_rejection_note }}
    </p>
@endif

<p style="margin:0 0 24px;font-size:15px;">
    {{ __('profile_photos.rejection_email_action') }}
</p>

<p style="margin:0;">
    <a href="{{ $profileUrl }}"
       style="display:inline-block;padding:12px 24px;background:#7c3aed;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
        {{ __('profile_photos.rejection_email_button') }}
    </a>
</p>
@endsection
