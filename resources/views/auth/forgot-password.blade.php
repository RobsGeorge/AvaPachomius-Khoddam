@extends('layouts.app')

@section('content')
<div class="container max-w-md mx-auto p-6 bg-white rounded shadow-md" dir="rtl">
    <h2 class="text-2xl font-bold mb-6 text-right">نسيت كلمة المرور</h2>

    @if(session('status'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded text-right">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 text-red-600 text-right">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>- {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('password.email') }}" method="POST" novalidate>
        @csrf

        <div class="mb-4 text-right">
            <label for="email" class="block mb-1 font-semibold">البريد الإلكتروني</label>
            <input
                type="email"
                name="email"
                id="email"
                value="{{ old('email') }}"
                required
                class="w-full border rounded px-3 py-2"
                placeholder="أدخل بريدك الإلكتروني"
                autofocus
            >
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
            إرسال رمز التحقق
        </button>
    </form>

    <p class="mt-4 text-sm text-gray-600 text-right">
        إذا تم العثور على البريد الإلكتروني في قاعدة بياناتنا، سوف نرسل لك رسالة. يرجى التحقق من صندوق الرسائل غير المرغوب فيها أيضًا.
    </p>
</div>
@endsection
