@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-md p-6 bg-white rounded shadow-md" dir="rtl">
    <h2 class="text-2xl mb-6 font-bold text-right">تسجيل الدخول</h2>

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-right">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="mb-4 text-green-600 text-right">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="block mb-1 text-right">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required
                   class="w-full border rounded px-3 py-2 text-right">
        </div>

        <div class="mb-4">
            <label for="password" class="block mb-1 text-right">كلمة المرور</label>
            <input id="password" type="password" name="password" required
                   class="w-full border rounded px-3 py-2 text-right">
        </div>

        <div class="mb-4 flex items-center justify-end">
            <label for="remember" class="ml-2 text-right">تذكرني</label>
            <input type="checkbox" name="remember" id="remember" class="ml-2">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
            تسجيل الدخول
        </button>

        <div class="mt-4 text-right">
            <a href="{{ route('password.request') }}" class="text-blue-600 hover:underline"> نسيت كلمة السر؟</a>
        </div>
    </form>
</div>
@endsection
