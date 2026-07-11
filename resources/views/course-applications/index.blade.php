@extends('layouts.app')

@section('title', __('course_applications.available_courses_title'))

@section('content')
@php
    use App\Models\CourseApplication;
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('course_applications.available_courses_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('course_applications.available_courses_intro') }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="mb-5">
        <h2 class="h5 mb-3">{{ __('course_applications.my_enrolled_courses') }}</h2>
        @if($enrolledCourses->isEmpty())
            <div class="alert alert-info mb-0">{{ __('course_applications.no_enrolled_courses') }}</div>
        @else
            <div class="row g-3">
                @foreach($enrolledCourses as $course)
                    <div class="col-md-6 col-lg-4">
                        <div class="app-card card shadow-sm h-100">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">{{ $course->title }}</h5>
                                @if($course->year)
                                    <p class="text-muted small mb-3">{{ $course->year }}</p>
                                @endif
                                <span class="badge bg-success align-self-start mb-3">{{ __('course_applications.enrolled') }}</span>
                                <a href="{{ route('curriculum.show', $course->course_id) }}" class="btn btn-primary btn-sm mt-auto">
                                    {{ __('pages.view_curriculum') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div>
        <h2 class="h5 mb-3">{{ __('course_applications.open_for_application') }}</h2>
        @if($openForms->isEmpty())
            <div class="alert alert-info mb-0">{{ __('course_applications.no_open_applications') }}</div>
        @else
            <div class="row g-3">
                @foreach($openForms as $form)
                    @php $status = $applicationStatuses[$form->course_id] ?? null; @endphp
                    <div class="col-md-6 col-lg-4">
                        <div class="app-card card shadow-sm h-100 border-primary">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">{{ $form->course?->title ?? $form->title }}</h5>
                                @if($form->course?->year)
                                    <p class="text-muted small mb-2">{{ $form->course->year }}</p>
                                @endif
                                @if($form->description)
                                    <p class="small text-muted-theme mb-2">{{ $form->description }}</p>
                                @endif
                                @if($status)
                                    <span class="badge bg-secondary align-self-start mb-2">{{ __('course_applications.status_'.$status) }}</span>
                                @else
                                    <span class="badge bg-primary align-self-start mb-2">{{ __('course_applications.can_apply') }}</span>
                                @endif
                                <div class="mt-auto d-grid gap-2">
                                    @if($status === CourseApplication::STATUS_NEEDS_CORRECTION)
                                        <a href="{{ route('courses.application.edit', $form->course_id) }}" class="btn btn-warning btn-sm">
                                            {{ __('course_applications.fix_application') }}
                                        </a>
                                    @elseif(in_array($status, [CourseApplication::STATUS_PENDING_REVIEW, CourseApplication::STATUS_REJECTED], true))
                                        <a href="{{ route('courses.application.status', $form->course_id) }}" class="btn btn-outline-primary btn-sm">
                                            {{ __('course_applications.view_status') }}
                                        </a>
                                    @else
                                        <a href="{{ route('courses.apply', $form->course_id) }}" class="btn btn-primary btn-sm">
                                            {{ __('course_applications.apply_now') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
