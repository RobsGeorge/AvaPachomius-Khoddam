@extends('layouts.app')

@section('title', __('auth.recover_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">
    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('auth.recover_title') }}</h2>
        <p class="text-muted-theme small">{{ __('auth.reset_form_intro') }}</p>
    </div>

    @if(session('status'))
        <div class="alert alert-success">
            <i class="bi bi-envelope-check me-1"></i>
            {{ session('status') }}
            <div class="mt-2 small">{{ __('auth.check_spam') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card mb-3">
        <div class="card-body p-4">
            <div class="alert alert-warning mb-4 py-3">
                <div class="d-flex gap-2">
                    <i class="bi bi-info-circle-fill flex-shrink-0"></i>
                    <div class="small">
                        <strong>{{ __('auth.reset_form_info_title') }}</strong>
                        <p class="mb-1 mt-1">{{ __('auth.reset_form_info_body') }}</p>
                        <p class="mb-0">{{ __('auth.check_spam') }}</p>
                    </div>
                </div>
            </div>

            <form action="{{ route('password.email') }}" method="POST" novalidate>
                @csrf

                <div class="mb-4">
                    <label for="email" class="form-label">{{ __('auth.email') }}</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="{{ __('auth.email_placeholder') }}"
                        autofocus
                    >
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-send"></i> {{ __('auth.send_reset_link') }}
                </button>
            </form>
        </div>

        <div class="card-footer py-3">
            <a href="{{ route('login') }}" class="text-muted-theme small">
                <i class="bi bi-arrow-left"></i> {{ __('auth.back_to_login') }}
            </a>
        </div>
    </div>
</div>
@endsection
