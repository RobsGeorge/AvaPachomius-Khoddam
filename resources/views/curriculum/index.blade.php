@extends('layouts.app')

@section('title', __('pages.curriculum'))

@section('content')
<div class="container py-4 animate-in">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('pages.curriculum') }}</h1>
        @if(auth()->user()->hasAnyRole(['admin', 'instructor']))
            <a href="{{ route('modules.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-collection"></i> {{ __('pages.modules_title') }}
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($courses->isEmpty() && ($applyCourses ?? collect())->isEmpty())
        <div class="alert alert-info">{{ __('pages.no_courses_for_curriculum') }}</div>
    @else
        @if(($applyCourses ?? collect())->isNotEmpty())
            <div class="mb-4">
                <h2 class="h5 mb-3">{{ __('course_applications.open_applications') }}</h2>
                <div class="row g-3">
                    @foreach($applyCourses as $form)
                        @php $status = $applicationStatuses[$form->course_id] ?? null; @endphp
                        <div class="col-md-6 col-lg-4">
                            <div class="app-card card shadow-sm h-100 border-primary">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">{{ $form->course?->title ?? $form->title }}</h5>
                                    @if($status)
                                        <span class="badge bg-secondary align-self-start mb-2">{{ __('course_applications.status_'.$status) }}</span>
                                    @endif
                                    <div class="mt-auto d-grid gap-2">
                                        @if($status === \App\Models\CourseApplication::STATUS_NEEDS_CORRECTION)
                                            <a href="{{ route('courses.application.edit', $form->course_id) }}" class="btn btn-warning btn-sm">
                                                {{ __('course_applications.fix_application') }}
                                            </a>
                                        @elseif(in_array($status, [\App\Models\CourseApplication::STATUS_PENDING_REVIEW, \App\Models\CourseApplication::STATUS_REJECTED], true))
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
            </div>
        @endif

        @if($courses->isNotEmpty())
        <p class="text-muted-theme mb-4">{{ __('pages.curriculum_by_module_hint') }}</p>
        <div class="row g-3">
            @foreach($courses as $course)
                <div class="col-md-6 col-lg-4">
                    <div class="app-card card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">{{ $course->title }}</h5>
                            @if($course->year)
                                <p class="text-muted small mb-3">{{ $course->year }}</p>
                            @endif
                            <a href="{{ route('curriculum.show', $course->course_id) }}"
                               class="btn btn-primary mt-auto">
                                <i class="bi bi-journal-bookmark"></i> {{ __('pages.view_curriculum') }}
                            </a>
                            @if(auth()->user()->hasAnyRole(['admin', 'instructor']))
                                <a href="{{ route('curriculum.admin', $course->course_id) }}"
                                   class="btn btn-outline-theme btn-sm mt-2">
                                    <i class="bi bi-pencil-square"></i> {{ __('pages.manage_curriculum') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    @endif
</div>
@endsection
