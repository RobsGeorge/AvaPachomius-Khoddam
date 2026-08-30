@extends('emails.layout')

@section('content')
<p style="margin:0 0 16px;font-size:16px;">
    {{ __('user_deletion.email_greeting', ['name' => $user->displayName()]) }}
</p>

<p style="margin:0 0 16px;font-size:15px;">
    @if($permanent)
        {{ __('user_deletion.email_body_hard') }}
    @else
        {{ __('user_deletion.email_body_soft') }}
    @endif
</p>

<p style="margin:0;font-size:14px;opacity:0.85;">
    {{ __('user_deletion.email_footer') }}
</p>
@endsection
