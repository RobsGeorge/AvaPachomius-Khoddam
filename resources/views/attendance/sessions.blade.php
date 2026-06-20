@extends('layouts.app')

@section('title', __('pages.attendance_confirm_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h2 class="page-title mb-4">{{ __('pages.attendance_confirm_title') }}</h2>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card mb-4">
        <div class="card-header">
            <i class="bi bi-person-badge"></i> {{ __('pages.scanned_student') }}
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                @if($user->profile_photo)
                    <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ __('pages.profile_photo') }}"
                         class="rounded-circle border profile-preview-img">
                @else
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center profile-preview-img">
                        <span class="text-muted-theme">{{ __('pages.no_photo') }}</span>
                    </div>
                @endif
            </div>

            <p class="mb-2"><strong>{{ __('pages.full_name') }}:</strong>
                {{ trim($user->first_name . ' ' . $user->second_name . ' ' . $user->third_name) }}
            </p>
            <p class="mb-2"><strong>{{ __('pages.national_id') }}:</strong> {{ $user->national_id ?? __('pages.not_available') }}</p>
            <p class="mb-2"><strong>{{ __('pages.phone') }}:</strong> {{ $user->mobile_number ?? __('pages.not_available') }}</p>
            <p class="mb-0"><strong>{{ __('pages.email') }}:</strong> {{ $user->email }}</p>
        </div>
    </div>

    <h3 class="page-title h5 mb-3">{{ __('pages.today_lectures', ['date' => $today]) }}</h3>

    @if($sessions->isEmpty())
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-calendar-x mt-1"></i>
            <div>
                <div>{{ __('pages.no_lectures_today') }}</div>
                <div class="mt-2">{{ __('pages.no_sessions_add_instruction') }}</div>
                <a href="{{ route('sessions.create') }}" class="btn btn-outline-theme btn-sm mt-2">
                    <i class="bi bi-plus-circle"></i> {{ __('pages.add_session') }}
                </a>
            </div>
        </div>
    @else
        @foreach($sessions as $session)
            @php
                $record = $existingAttendance->get($session->session_id);
            @endphp
            <div class="app-card card mb-3">
                <div class="card-body">
                    <h4 class="h6 page-title mb-2">{{ $session->session_title }}</h4>
                    <p class="mb-1 text-muted-theme small">
                        <i class="bi bi-calendar-event"></i>
                        {{ $session->session_date?->format('d/m/Y') }}
                    </p>
                    @if($session->course)
                        <p class="mb-3 text-muted-theme small">
                            <i class="bi bi-book"></i> {{ $session->course->title }}
                        </p>
                    @endif

                    @if($session->isAttendanceClosed() && ! $record)
                        <div class="alert alert-secondary mb-0 d-flex align-items-start gap-2">
                            <i class="bi bi-lock-fill mt-1"></i>
                            <div>{{ __('pages.attendance_session_closed') }}</div>
                        </div>
                    @elseif($record)
                        <div class="alert alert-info mb-0 d-flex align-items-start gap-2">
                            <i class="bi bi-info-circle-fill mt-1"></i>
                            <div>
                                {{ __('pages.attendance_already_recorded_for_session') }}
                                @if($record->attendance_time)
                                    <div class="small mt-1">{{ __('pages.recorded_at') }}: {{ $record->attendance_time->format('d/m/Y H:i') }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <form action="{{ route('attendance.record', $session->session_id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="student_user_id" value="{{ $userId }}">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check2-circle"></i> {{ __('pages.confirm_attendance') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
