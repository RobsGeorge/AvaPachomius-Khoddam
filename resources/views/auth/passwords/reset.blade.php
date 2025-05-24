@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-md p-6 bg-white rounded shadow-md">
    <h2 class="text-2xl mb-6 font-bold">Reset Password</h2>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-4">
            <label for="email" class="block mb-1">Email Address</label>
            <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus
                class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label for="password" class="block mb-1">New Password</label>
            <input id="password" type="password" name="password" required
                class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-4">
            <label for="password-confirm" class="block mb-1">Confirm Password</label>
            <input id="password-confirm" type="password" name="password_confirmation" required
                class="w-full border rounded px-3 py-2">
        </div>

        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
            Reset Password
        </button>
    </form>
</div>
@endsection
