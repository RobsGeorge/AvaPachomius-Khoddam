@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
    <h1 class="text-2xl font-bold mb-6 text-right">الاسم: </h1>
    <h1 class="text-2xl font-bold mb-6 text-right">{{ $user->first_name }}{{$user->second_name}} {{$user->third_name}}</h1>
    <div class="relative w-32 h-32 rounded-full overflow-hidden mx-auto mb-4 cursor-pointer">
        @if($user->profile_photo)
            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="صورة الملف الشخصي"
                class="w-full h-full object-cover border rounded-full">
        @else
            <div class="w-full h-full bg-gray-200 flex items-center justify-center rounded-full">
                <span class="text-gray-500">لا صورة</span>
            </div>
        @endif
    </div>
</br>

    <h1 class="mb-4">محاضرات اليوم ({{ date('Y-m-d') }})</h1>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-3">{{ session('error') }}</div>
    @endif

    @if($sessions->isEmpty())
        <p>لا توجد محاضرات اليوم.</p>
    @else
        <div class="list-group">
        @foreach ($sessions as $session)
            <form action="{{ route('attendance.record', $session->session_id) }}" method="POST" class="mb-2">
                @csrf
                <input type="hidden" name="student_user_id" value="{{ $userId }}">
                <button type="submit" class="btn btn-primary w-full">
                    {{ $session->session_title }} - {{ $session->session_date }}
                </button>
            </form>
        @endforeach
        </div>
    @endif
</div>
@endsection
