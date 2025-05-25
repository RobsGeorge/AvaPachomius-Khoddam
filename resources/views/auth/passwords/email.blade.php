@extends('layouts.app')

@section('content')
<div class="container">
    <h1>استعادة كلمة المرور</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">البريد الإلكتروني</label>
            <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" required autofocus>

            @error('email')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary">إرسال رابط إعادة التعيين</button>
    </form>
</div>
@endsection
