@extends('layouts.app')

@section('content')
<div class="container" dir="rtl" style="text-align: right;">
    <h4 class="mb-4">تعيين كلمة المرور</h4>

    {{-- Success message --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    {{-- Error message --}}
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('password.store') }}" method="POST" novalidate>
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
                minlength="8"
                placeholder="أدخل كلمة المرور الجديدة"
                autocomplete="new-password"
            >
            <div class="invalid-feedback">
                كلمة المرور مطلوبة ويجب أن تكون 8 أحرف على الأقل.
            </div>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">تأكيد كلمة المرور</label>
            <input
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                class="form-control @error('password_confirmation') is-invalid @enderror"
                required
                minlength="8"
                placeholder="أعد كتابة كلمة المرور"
                autocomplete="new-password"
            >
            <div class="invalid-feedback">
                يجب تأكيد كلمة المرور بشكل صحيح.
            </div>
        </div>

        <button type="submit" class="btn btn-primary">حفظ</button>
    </form>
</div>

<script>
// Bootstrap custom validation example
(() => {
    'use strict'

    const forms = document.querySelectorAll('form')

    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            // Check if passwords match
            const pwd = form.querySelector('#password');
            const confirmPwd = form.querySelector('#password_confirmation');

            if (pwd.value !== confirmPwd.value) {
                confirmPwd.setCustomValidity('كلمتا المرور غير متطابقتين');
            } else {
                confirmPwd.setCustomValidity('');
            }

            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }

            form.classList.add('was-validated')
        }, false)
    })
})();
</script>
@endsection
