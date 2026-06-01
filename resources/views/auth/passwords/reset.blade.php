@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-md p-6 bg-white rounded shadow-md" dir="rtl" style="text-align: right;">
    <h2 class="text-2xl mb-6 font-bold">إعادة تعيين كلمة المرور</h2>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="reset-password-form" method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-4">
            <label for="email" class="block mb-1">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus
                class="w-full border rounded px-3 py-2 form-control">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">كلمة المرور الجديدة</label>
            <input id="password" type="password" name="password" required
                class="w-full border rounded px-3 py-2 form-control" autocomplete="new-password">
        </div>

        <x-password-requirements
            password-id="password"
            confirm-id="password_confirmation"
            form-id="reset-password-form"
        />

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">تأكيد كلمة المرور</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                class="w-full border rounded px-3 py-2 form-control" autocomplete="new-password">
        </div>

        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            إعادة تعيين كلمة المرور
        </button>
    </form>
</div>
@endsection
