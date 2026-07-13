@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
@php
    use App\Support\NavigationHub;
    $hasSystem = NavigationHub::hasSystem(Auth::user());
    $hasSuperadmin = NavigationHub::hasSuperadmin(Auth::user());
@endphp
<div class="animate-in" style="max-width: 920px; margin: 0 auto;">

    <h1 class="page-title">{{ __('dashboard.title') }}</h1>

    @if(!empty($showNoCoursesCta))
        <div class="app-card card shadow-sm mb-4 border-warning border-opacity-50">
            <div class="card-body text-center py-4">
                <i class="bi bi-journal-x fs-1 text-muted-theme d-block mb-3"></i>
                <h2 class="h5">{{ __('course_context.no_active_courses') }}</h2>
                <p class="text-muted-theme mb-0">{{ __('course_context.no_active_courses_hint') }}</p>
            </div>
        </div>
    @endif

    <div class="text-center my-5">
        <p class="display-5 fw-bold page-title mb-0">
            {{ __('dashboard.hello', ['name' => Auth::user()->first_name ?? __('dashboard.user_fallback')]) }}
        </p>
    </div>

    @if(isset($homepageAnnouncements) && $homepageAnnouncements->isNotEmpty())
        <div class="app-card card shadow-sm mb-4">
            <div class="card-header fw-semibold">
                <i class="bi bi-megaphone"></i> {{ __('announcements.title') }}
            </div>
            <div class="card-body d-flex flex-column gap-3">
                @foreach($homepageAnnouncements as $announcement)
                    <a href="{{ route('announcements.show', $announcement) }}" class="text-decoration-none announcement-home-card">
                        <strong class="d-block mb-1">{{ $announcement->title }}</strong>
                        <span class="text-muted-theme small">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if(isset($completedCourses) && $completedCourses->isNotEmpty())
        <div class="app-card card shadow-sm mb-4 border-primary border-opacity-50">
            <div class="card-header fw-semibold">
                <i class="bi bi-mortarboard"></i> {{ __('course_graduation.final_grades_title') }}
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @foreach($completedCourses as $course)
                    <a href="{{ route('courses.final-grades', $course->course_id) }}" class="btn btn-outline-primary btn-sm text-start">
                        {{ $course->title }} — {{ $course->year }}
                        <span class="float-end"><i class="bi bi-chevron-right"></i></span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if($todayBirthdays->isNotEmpty())
        <div class="app-card card shadow-sm mb-4 border-success border-opacity-50 student-data-hub">
            <div class="card-header bg-success bg-opacity-10 fw-semibold">
                <i class="bi bi-balloon-heart"></i> {{ __('students.birthdays_today_heading') }}
                <span class="badge bg-success ms-1">{{ $todayBirthdays->count() }}</span>
            </div>
            <div class="card-body p-2 p-md-3">
                @foreach($todayBirthdays as $student)
                    @if(Auth::user()->isStudent() && ! Auth::user()->isInstructorOrAdmin())
                        @include('students.partials.birthday-peer-card', [
                            'student' => $student,
                            'whatsappMessage' => __('students.whatsapp_birthday_message'),
                        ])
                    @else
                        @include('students.partials.roster-student-card', [
                            'student' => $student,
                            'whatsappMessage' => __('students.whatsapp_birthday_message'),
                            'showNationalId' => false,
                        ])
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-md-6">
            <a href="{{ route('notifications.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none position-relative {{ !empty($unreadNotificationCount) && $unreadNotificationCount > 0 ? 'hub-tile-highlight' : '' }}">
                <h3><i class="bi bi-bell"></i> {{ __('notifications.dashboard_tile') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('notifications.dashboard_tile_desc') }}</p>
                @if(!empty($unreadNotificationCount) && $unreadNotificationCount > 0)
                    <span class="nav-notification-badge position-absolute top-0 end-0 m-3">{{ $unreadNotificationCount }}</span>
                @endif
            </a>
        </div>

        <div class="col-md-6">
            <a href="{{ route('hubs.academic') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-mortarboard"></i> {{ __('dashboard.academic_hub') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.academic_hub_desc') }}</p>
            </a>
        </div>

        @if(Auth::user()->isStudent() && ! Auth::user()->isInstructorOrAdmin())
            <div class="col-md-6">
                <a href="{{ route('students.birthdays') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-cake2"></i> {{ __('dashboard.birthdays') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.birthdays_desc') }}</p>
                </a>
            </div>
        @endif

        @if($hasSystem)
            <div class="col-md-6">
                <a href="{{ route('hubs.system') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-gear"></i> {{ __('dashboard.system_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.system_hub_desc') }}</p>
                </a>
            </div>
        @endif

        @if($hasSuperadmin)
            <div class="col-md-6">
                <a href="{{ route('superadmin.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none border border-danger border-opacity-25">
                    <h3><i class="bi bi-shield-lock-fill text-danger"></i> {{ __('dashboard.superadmin_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.superadmin_hub_desc') }}</p>
                </a>
            </div>
        @endif

        <div class="col-md-6">
            <a href="{{ route('profile') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-person-circle"></i> {{ __('dashboard.profile') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.profile_desc') }}</p>
            </a>
        </div>
    </div>
</div>
@endsection
