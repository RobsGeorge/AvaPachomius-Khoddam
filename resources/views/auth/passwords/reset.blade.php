@extends('layouts.app')

@section('title', __('password.reset_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">
    <h2 class="page-title mb-4">{{ __('password.reset_title') }}</h2>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form id="reset-password-form" method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-4">
                    <label for="email" class="form-label">{{ __('auth.email') }}</label>
                    <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus
                        class="form-control">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('auth.new_password') }}</label>
                    <input id="password" type="password" name="password" required
                        class="form-control" autocomplete="new-password">
                </div>

                <x-password-requirements
                    password-id="password"
                    confirm-id="password_confirmation"
                    form-id="reset-password-form"
                />

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">{{ __('auth.confirm_password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        class="form-control" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    {{ __('password.reset_button') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
