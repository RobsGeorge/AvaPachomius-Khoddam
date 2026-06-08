@extends('layouts.app')

@section('title', __('auth.login_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">

    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('auth.login_title') }}</h2>
        <p class="text-muted-theme small">{{ __('app.tagline') }}</p>
    </div>

    @if(session('login_required'))
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-shield-lock-fill fs-5"></i>
            <span>{{ session('login_required') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror"
                           required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('auth.password') }}</label>
                    <input id="password" type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 d-flex align-items-center gap-2">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input">
                    <label for="remember" class="form-check-label">{{ __('auth.remember') }}</label>
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
            @if(config('demo.enabled'))
                <a href="{{ route('demo.index') }}" class="btn btn-info btn-sm text-dark">
                    <i class="bi bi-play-circle"></i> {{ __('demo.login_link') }}
                </a>
            @endif
            @unless(config('demo.block_registration'))
                <a href="{{ route('register') }}" class="btn btn-outline-theme btn-sm">
                    <i class="bi bi-person-plus"></i> {{ __('auth.new_account') }}
                </a>
            @endunless
        </div>
    </div>

</div>
@endsection
