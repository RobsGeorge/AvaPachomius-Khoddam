@extends('layouts.app')

@section('body_class', 'auth-form-page')

@section('title', __('auth.login_otp_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">

    <div class="text-center mb-4">
        <a href="{{ url('/') }}" class="d-inline-flex flex-column align-items-center text-decoration-none mb-3">
            <x-application-logo class="brand-mark-lg" aria-hidden="true" />
            <span class="brand-wordmark mt-2">{{ __('app.name') }}</span>
            <span class="text-muted-theme small mt-1">{{ __('app.institute_name') }}</span>
        </a>
        <h2 class="page-title mb-1">{{ __('auth.login_otp_title') }}</h2>
        @if(session( \App\Services\Auth\LoginOtpChallengeService::SESSION_CHANNEL_KEY) === 'mobile')
            <p class="text-muted-theme small">{{ __('auth.login_otp_hint_mobile') }}</p>
        @else
            <p class="text-muted-theme small">{{ __('auth.login_otp_hint_email') }}</p>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

<div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('login.otp.verify') }}">
                @csrf

                <div class="mb-3">
                    <label for="otp" class="form-label">{{ __('auth.otp_code') }}</label>
                    <input id="otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                           class="form-control @error('otp') is-invalid @enderror"
                           required autofocus autocomplete="one-time-code">
                    @error('otp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 d-flex align-items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1">
                    <label for="remember" class="form-check-label">{{ __('auth.remember') }}</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
                    <i class="bi bi-shield-check"></i> {{ __('auth.otp_verify') }}
                </button>
            </form>

            <form method="POST" action="{{ route('login.otp.resend') }}">
                @csrf
                <button type="submit" class="btn btn-outline-theme w-100">
                    <i class="bi bi-arrow-repeat"></i> {{ __('auth.otp_resend') }}
                </button>
            </form>
        </div>

        <div class="card-footer py-3 text-center">
            <a href="{{ route('login') }}" class="text-muted-theme small">
                <i class="bi bi-arrow-left"></i> {{ __('auth.back_to_login') }}
            </a>
        </div>
    </div>

</div>
@endsection
