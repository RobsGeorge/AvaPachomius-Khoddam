@extends('layouts.app')

@section('title', __('pages.record_attendance'))

@section('content')
<div class="container py-4 animate-in">
    <h2 class="page-title mb-3">{{ $user->first_name . ' ' . $user->second_name . ' ' . $user->third_name }}</h2>

    <div class="text-center mb-4">
        @if($user->profile_photo)
            <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ __('pages.profile_photo') }}"
                class="rounded-circle border" style="width:128px;height:128px;object-fit:cover;">
        @else
            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width:128px;height:128px;">
                <span class="text-muted-theme">{{ __('pages.no_photo') }}</span>
            </div>
        @endif
    </div>

    <h1 class="page-title h4 mb-4">{{ __('pages.today_lectures', ['date' => date('Y-m-d')]) }}</h1>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-3">{{ session('error') }}</div>
    @endif

    @if($sessions->isEmpty())
        <p class="text-muted-theme">{{ __('pages.no_lectures_today') }}</p>
    @else
        <div class="list-group">
        @foreach ($sessions as $session)
            <form action="{{ route('attendance.record', $session->session_id) }}" method="POST" class="mb-2">
                @csrf
                <input type="hidden" name="student_user_id" value="{{ $userId }}">
                <button type="submit" class="btn btn-primary w-100">
                    {{ $session->session_title }} - {{ $session->session_date }}
                </button>
            </form>
        @endforeach
        </div>
    @endif
</div>
@endsection
