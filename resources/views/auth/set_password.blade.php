@extends('layouts.app')

@section('content')
<div class="container" dir="rtl" style="text-align: right;">
    <h4 class="mb-4">تعيين كلمة المرور</h4>

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

    <form id="set-password-form" action="{{ route('password.store') }}" method="POST" novalidate>
        @csrf
        <input type="hidden" name="user_id" value="{{ $user_id }}">

        <div class="mb-3">
            <label for="password" class="form-label">كلمة المرور الجديدة</label>
            <input
                type="password"
                name="password"
                id="password"
                class="form-control @error('password') is-invalid @enderror"
                required
                placeholder="أدخل كلمة المرور الجديدة"
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
            <label for="password_confirmation" class="form-label">تأكيد كلمة المرور</label>
            <input
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                class="form-control @error('password_confirmation') is-invalid @enderror"
                required
                placeholder="أعد كتابة كلمة المرور"
                autocomplete="new-password"
            >
            @error('password_confirmation')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">حفظ</button>
    </form>
</div>
@endsection
