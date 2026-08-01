@extends('layouts.app')

@section('body_class', 'auth-form-page')
@section('title', __('auth.otp_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:460px;">
    <div class="text-center mb-4">
        <h2 class="page-title mb-1">{{ __('auth.otp_title') }}</h2>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('recovery.otp.verify') }}">
                @csrf
                <div class="mb-4">
                    <label class="form-label" for="otp">{{ __('auth.otp_code') }}</label>
                    <input id="otp" name="otp" class="form-control" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
                </div>
                <button type="submit" class="btn btn-primary w-100">{{ __('auth.otp_verify') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
