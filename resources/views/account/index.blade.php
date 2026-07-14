@extends('layouts.app')

@section('title', __('account.title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <h1 class="page-title mb-1">{{ __('account.title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('account.subtitle') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Profile --}}
    <div class="app-card card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-1"><i class="bi bi-person-badge" aria-hidden="true"></i> {{ __('account.profile_heading') }}</h2>
            <p class="text-muted-theme small mb-3">{{ __('account.profile_desc') }}</p>
            <p class="mb-3"><strong>{{ $fullName }}</strong><br>
                <span class="text-muted-theme small">{{ $user->email }}</span></p>
            <a href="{{ route('profile') }}" class="btn btn-outline-primary btn-sm">{{ __('account.profile_manage') }}</a>
        </div>
    </div>

    {{-- Password & security --}}
    <div class="app-card card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-1"><i class="bi bi-shield-lock" aria-hidden="true"></i> {{ __('account.security_heading') }}</h2>
            <p class="text-muted-theme small mb-3">{{ __('account.security_desc') }}</p>

            <form method="POST" action="{{ route('account.password.update') }}" class="row g-3" style="max-width:520px;">
                @csrf
                @method('PUT')
                <div class="col-12">
                    <label for="current_password" class="form-label">{{ __('account.current_password') }}</label>
                    <input type="password" name="current_password" id="current_password"
                           class="form-control @error('current_password') is-invalid @enderror"
                           autocomplete="current-password" required>
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="new_password" class="form-label">{{ __('account.new_password') }}</label>
                    <input type="password" name="password" id="new_password"
                           class="form-control @error('password') is-invalid @enderror"
                           autocomplete="new-password" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="password_confirmation" class="form-label">{{ __('account.confirm_password') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           class="form-control" autocomplete="new-password" required>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">{{ __('account.change_password') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Notifications --}}
    <div class="app-card card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-1"><i class="bi bi-bell" aria-hidden="true"></i> {{ __('account.notifications_heading') }}</h2>
            <p class="text-muted-theme small mb-2">{{ __('account.notifications_desc') }}</p>
            <p class="text-muted-theme small mb-3">{{ __('account.notifications_types', ['count' => $notificationTypeCount]) }}</p>
            <a href="{{ route('notifications.settings') }}" class="btn btn-outline-primary btn-sm">{{ __('account.notifications_manage') }}</a>
        </div>
    </div>

    {{-- Appearance & language --}}
    <div class="app-card card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-1"><i class="bi bi-palette" aria-hidden="true"></i> {{ __('account.appearance_heading') }}</h2>
            <p class="text-muted-theme small mb-3">{{ __('account.appearance_desc') }}</p>

            <div class="mb-3">
                <span class="form-label d-block mb-1">{{ __('account.theme') }}</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-theme-toggle
                        data-label-light="{{ __('account.theme_light') }}"
                        data-label-dark="{{ __('account.theme_dark') }}"
                        aria-pressed="false">
                    <i class="bi bi-moon-stars" aria-hidden="true"></i>
                    <span data-theme-label>{{ __('account.theme_dark') }}</span>
                </button>
            </div>

            <div>
                <span class="form-label d-block mb-1">{{ __('account.language') }}</span>
                <div class="btn-group" role="group" aria-label="{{ __('account.language') }}">
                    @foreach($supportedLocales as $localeCode)
                        <a href="{{ route('locale.switch', $localeCode) }}"
                           class="btn btn-sm {{ app()->getLocale() === $localeCode ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ config('translation.locale_labels.' . $localeCode, $localeCode) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Your data --}}
    <div class="app-card card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-1"><i class="bi bi-download" aria-hidden="true"></i> {{ __('account.data_heading') }}</h2>
            <p class="text-muted-theme small mb-3">{{ __('account.data_desc') }}</p>
            <a href="{{ route('account.export') }}" class="btn btn-outline-primary btn-sm">{{ __('account.data_export') }}</a>
        </div>
    </div>
</div>
@endsection
