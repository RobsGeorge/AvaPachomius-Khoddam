@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
<div class="animate-in" style="max-width: 920px; margin: 0 auto;">

    <h1 class="page-title">{{ __('dashboard.title') }}</h1>

    <div class="text-center my-5">
        <p class="display-5 fw-bold page-title mb-0">
            {{ __('dashboard.hello', ['name' => Auth::user()->first_name ?? __('dashboard.user_fallback')]) }}
        </p>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="app-tile h-100">
                <h3><i class="bi bi-person-circle"></i> {{ __('dashboard.profile') }}</h3>
                <p class="text-muted-theme">{{ __('dashboard.profile_desc') }}</p>
                <a href="{{ route('profile') }}" class="btn btn-primary">{{ __('dashboard.view_profile') }}</a>
            </div>
        </div>

        <div class="col-md-6">
            <div class="app-tile h-100">
                <h3><i class="bi bi-calendar-check"></i> {{ __('dashboard.attendance') }}</h3>
                <p class="text-muted-theme">{{ __('dashboard.attendance_desc') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('attendance.my') }}" class="btn btn-primary">{{ __('dashboard.my_attendance') }}</a>
                    @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                        <a href="{{ route('attendance.all') }}" class="btn btn-outline-theme">{{ __('dashboard.all_attendance') }}</a>
                        <a href="{{ route('attendance.report') }}" class="btn btn-outline-theme">{{ __('dashboard.attendance_report') }}</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="app-tile h-100">
                <h3><i class="bi bi-journal-text"></i> {{ __('dashboard.assignments') }}</h3>
                <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('assignments.index') }}" class="btn btn-primary">{{ __('dashboard.view_assignments') }}</a>
                @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <a href="{{ route('assignments.dashboard') }}" class="btn btn-outline-theme">{{ __('dashboard.manage_assignments') }}</a>
                @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="app-tile h-100">
                <h3><i class="bi bi-mortarboard"></i> {{ __('dashboard.curriculum') }}</h3>
                <p class="text-muted-theme">{{ __('dashboard.curriculum_desc') }}</p>
                <a href="{{ route('curriculum.index') }}" class="btn btn-primary">{{ __('dashboard.view_curriculum') }}</a>
            </div>
        </div>

        <div class="col-md-6">
            <div class="app-tile h-100">
                <h3><i class="bi bi-calendar-event"></i> {{ __('dashboard.events') }}</h3>
                <p class="text-muted-theme">{{ __('dashboard.events_desc') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('events.index') }}" class="btn btn-primary">{{ __('dashboard.view_events') }}</a>
                    @if(Auth::user()->isEventAdmin())
                        <a href="{{ route('events.admin.index') }}" class="btn btn-outline-theme">{{ __('dashboard.manage_events') }}</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="app-tile h-100">
                <h3><i class="bi bi-patch-check"></i> {{ __('dashboard.exams') }}</h3>
                <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('exams.index') }}" class="btn btn-primary">{{ __('dashboard.view_exams') }}</a>
                @if(Auth::user()->roles->contains('role_name', 'admin') || Auth::user()->roles->contains('role_name', 'instructor'))
                    <a href="{{ route('exams.dashboard') }}" class="btn btn-outline-theme">{{ __('dashboard.manage_exams') }}</a>
                @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
