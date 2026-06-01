@extends('layouts.app')

@section('title', __('auth.forgot_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">
    <h2 class="page-title mb-4">{{ __('auth.forgot_title') }}</h2>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
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

    <div class="app-card card">
        <div class="card-body p-4">
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
                        class="form-control"
                        placeholder="{{ __('auth.email_placeholder') }}"
                        autofocus
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    {{ __('auth.send_otp') }}
                </button>
            </form>
        </div>
    </div>

    <p class="mt-4 text-muted-theme small">
        {{ __('auth.forgot_hint') }}
    </p>
</div>
@endsection
