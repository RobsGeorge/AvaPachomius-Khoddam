@extends('layouts.app')

@section('title', __('people_onboarding.claim_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width: 480px;">
    <h1 class="page-title mb-3">{{ __('people_onboarding.claim_title') }}</h1>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @unless($otpVerified)
        <form method="POST" action="{{ route('invitations.verify-otp', $token) }}" class="app-card card shadow-sm">
            @csrf
            <div class="card-body">
                <label class="form-label">{{ __('people_onboarding.claim_otp') }}</label>
                <input type="text" name="otp" class="form-control mb-3" inputmode="numeric" maxlength="6" required>
                <button class="btn btn-primary w-100" type="submit">{{ __('people_onboarding.claim_verify') }}</button>
            </div>
        </form>
    @else
        <form method="POST" action="{{ route('invitations.accept', $token) }}" class="app-card card shadow-sm">
            @csrf
            <div class="card-body">
                <label class="form-label">{{ __('people_onboarding.claim_password') }}</label>
                <input type="password" name="password" class="form-control mb-3" required minlength="8">
                <label class="form-label">{{ __('people_onboarding.claim_password_confirm') }}</label>
                <input type="password" name="password_confirmation" class="form-control mb-3" required minlength="8">
                <button class="btn btn-primary w-100" type="submit">{{ __('people_onboarding.claim_submit') }}</button>
            </div>
        </form>
    @endunless
</div>
@endsection
