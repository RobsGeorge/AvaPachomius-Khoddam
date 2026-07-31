@extends('layouts.app')

@section('body_class', 'auth-form-page')

@section('title', __('auth.login_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">

    <div class="text-center mb-4">
        <a href="{{ url('/') }}" class="d-inline-flex flex-column align-items-center text-decoration-none mb-3">
            <x-deaconia-logotype class="brand-logotype-lg" aria-hidden="true" />
            <span class="brand-wordmark-ar mt-2">دياكونيا</span>
            <span class="text-muted-theme small mt-1">{{ __('app.institute_name') }}</span>
        </a>
        <h2 class="page-title mb-1">{{ __('auth.login_title') }}</h2>
        <p class="text-muted-theme small">{{ __('app.tagline') }}</p>
    </div>

    @if(session('login_required'))
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-shield-lock-fill fs-5"></i>
            <span>{{ session('login_required') }}</span>
        </div>
    @endif

<div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="identifier" class="form-label">{{ __('auth.identifier') }}</label>
                    <input id="identifier" type="text" name="identifier" value="{{ old('identifier') }}"
                           class="form-control @error('identifier') is-invalid @enderror"
                           placeholder="{{ __('auth.identifier_placeholder') }}"
                           required autofocus>
                    @error('identifier')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ __('auth.login_identifier_hint') }}</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right"></i> {{ __('auth.login_button') }}
                </button>
            </form>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <a href="{{ route('password.request') }}" class="text-muted-theme small">
                <i class="bi bi-key"></i> {{ __('auth.forgot_password') }}
            </a>
            <a href="{{ route('register') }}" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-person-plus"></i> {{ __('auth.new_account') }}
            </a>
        </div>
    </div>

</div>
@endsection
