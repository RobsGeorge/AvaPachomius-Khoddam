@extends('layouts.app')

@section('content')
<div class="container py-5" style="max-width:460px;" dir="rtl">

    <div class="text-center mb-4">
        <h2 class="fw-bold" style="color:#7c3aed;">تسجيل الدخول</h2>
        <p class="text-muted small">إعداد خدام 2025</p>
    </div>

    {{-- Redirected-here alert --}}
    @if(session('login_required'))
        <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-shield-lock-fill fs-5"></i>
            <span>{{ session('login_required') }}</span>
        </div>
    @endif

    {{-- Validation errors --}}
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Success (e.g. after password set) --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">البريد الإلكتروني</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="form-control text-end @error('email') is-invalid @enderror"
                           required autofocus>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">كلمة المرور</label>
                    <input id="password" type="password" name="password"
                           class="form-control text-end @error('password') is-invalid @enderror"
                           required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 d-flex align-items-center justify-content-end gap-2">
                    <label for="remember" class="form-check-label">تذكرني</label>
                    <input type="checkbox" name="remember" id="remember" class="form-check-input">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> تسجيل الدخول
                </button>
            </form>
        </div>

        <div class="card-footer bg-light d-flex justify-content-between align-items-center py-3">
            <a href="{{ route('password.request') }}" class="text-muted small">
                <i class="bi bi-key"></i> نسيت كلمة السر؟
            </a>
            <a href="{{ route('register') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person-plus"></i> حساب جديد
            </a>
        </div>
    </div>

</div>
@endsection
