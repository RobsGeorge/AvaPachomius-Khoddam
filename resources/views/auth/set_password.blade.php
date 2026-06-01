@extends('layouts.app')

@section('title', __('auth.set_password'))

@section('content')
<div class="container py-4 animate-in" style="max-width:520px;">
    <h2 class="page-title mb-4">{{ __('auth.set_password') }}</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
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
            <form id="set-password-form" action="{{ route('password.set.store') }}" method="POST" novalidate>
                @csrf
                <input type="hidden" name="user_id" value="{{ $user_id }}">

                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('auth.new_password') }}</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control @error('password') is-invalid @enderror"
                        required
                        placeholder="{{ __('password.placeholder_new') }}"
                        autocomplete="new-password"
                    >
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <x-password-requirements
                    password-id="password"
                    confirm-id="password_confirmation"
                    form-id="set-password-form"
                />

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">{{ __('auth.confirm_password') }}</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        id="password_confirmation"
                        class="form-control @error('password_confirmation') is-invalid @enderror"
                        required
                        placeholder="{{ __('password.placeholder_confirm') }}"
                        autocomplete="new-password"
                    >
                    @error('password_confirmation')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
