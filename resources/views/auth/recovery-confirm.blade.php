@extends('layouts.app')

@section('body_class', 'auth-form-page')
@section('title', __('auth.recovery_confirm_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">
    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('auth.recovery_confirm_title') }}</h2>
        <p class="text-muted-theme small">{{ __('auth.recovery_confirm_intro') }}</p>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('recovery.confirm.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="challenge_id">{{ __('auth.recovery_challenge_id') }}</label>
                    <input id="challenge_id" name="challenge_id" type="number" class="form-control" required
                           value="{{ old('challenge_id', $challengeId) }}">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="otp">{{ __('auth.otp_code') }}</label>
                    <input id="otp" name="otp" class="form-control" required inputmode="numeric" maxlength="6">
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('auth.otp_verify') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
