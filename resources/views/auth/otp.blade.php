@extends('layouts.app')

@section('title', 'التحقق من OTP')

@section('content')
<div class="container py-5" dir="rtl">
    <div class="row justify-content-center">
        <div class="col-md-6">
            @if(session('success'))
                <div class="alert alert-success text-end">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger text-end">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-header text-center fw-bold">أدخل رمز التحقق</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('otp.verify') }}">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $userId }}">
                        <div class="mb-3 text-end">
                            <label for="otp" class="form-label">رمز التحقق</label>
                            <input type="text" name="otp" class="form-control" maxlength="6" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">تحقق</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('otp.resend') }}" class="mt-3 text-end">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $userId }}">
                        <button type="submit" class="btn btn-link">إعادة إرسال الرمز</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
