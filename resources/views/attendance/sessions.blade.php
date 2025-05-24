@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
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
