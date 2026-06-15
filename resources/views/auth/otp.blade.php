@extends('layouts.app')

@section('title', __('auth.otp_title'))

@section('content')
<div class="container py-5 animate-in">
    <div class="row justify-content-center">
        <div class="col-md-6">
            @if(session('success'))
                <div class="alert alert-success">
                    <i class="bi bi-envelope-check me-1"></i>
                    {{ session('success') }}
                    <div class="mt-2 small">{{ __('auth.check_spam') }}</div>
                </div>
            @endif
            @if(session('pending_registration_resume'))
                <div class="alert alert-warning">
                    <i class="bi bi-hourglass-split me-1"></i>
                    {{ __('register.pending_otp_message') }}
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="app-card card">
                <div class="card-header text-center fw-bold page-title">{{ __('auth.otp_enter') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('otp.verify') }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $userId }}">
                        <div class="mb-3">
                            <label for="otp" class="form-label">{{ __('auth.otp_code') }}</label>
                            <input type="text" name="otp" id="otp" class="form-control" maxlength="6" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">{{ __('auth.otp_verify') }}</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('otp.resend') }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $userId }}">
                        <button type="submit" class="btn btn-link">{{ __('auth.otp_resend') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
