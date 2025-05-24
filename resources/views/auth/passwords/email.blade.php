@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-md p-6 bg-white rounded shadow-md">
    <h2 class="text-2xl mb-6 font-bold">Reset Password</h2>

    @if (session('status'))
        <div class="mb-4 text-green-600">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-4">
            <label for="email" class="block mb-1">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                class="w-full border rounded px-3 py-2">
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Send Password Reset Link
        </button>
    </form>
</div>
@endsection
