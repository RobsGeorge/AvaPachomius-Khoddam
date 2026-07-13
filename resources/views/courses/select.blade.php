@extends('layouts.app')

@section('title', __('course_context.select_title'))

@section('content')
<div class="container py-5 animate-in">
    <div class="text-center mb-5">
        <p class="text-muted-theme small mb-1">{{ __('app.institute_name') }}</p>
        <h1 class="page-title mb-2">{{ __('course_context.select_title') }}</h1>
        <p class="text-muted-theme mb-0 mx-auto" style="max-width: 36rem;">{{ __('course_context.select_intro') }}</p>
    </div>

@if($courses->isEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-journal-x fs-1 text-muted-theme d-block mb-3"></i>
                <h2 class="h5">{{ __('course_context.no_active_courses') }}</h2>
                <p class="text-muted-theme mb-0">{{ __('course_context.no_active_courses_hint') }}</p>
            </div>
        </div>
    @else
        <div class="row g-4 justify-content-center">
            @foreach($courses as $row)
                @php
                    /** @var \App\Models\Course $course */
                    $course = $row['course'];
                    $role = $row['role'];
                    $isCurrent = ($currentCourse?->course_id ?? null) === $course->course_id;
                    $statusLabel = match ($course->status) {
                        \App\Models\Course::STATUS_GRADING_LOCKED => __('course_context.status_grading_locked'),
                        \App\Models\Course::STATUS_ANNOUNCED => __('course_context.status_announced'),
                        default => __('course_context.status_active'),
                    };
                @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="app-card card h-100 shadow-sm {{ $isCurrent ? 'border-primary' : '' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h2 class="h5 mb-0">{{ $course->localizedTitle() }}</h2>
                                @if($course->status !== \App\Models\Course::STATUS_ACTIVE)
                                    <span class="badge bg-secondary text-nowrap">{{ $statusLabel }}</span>
                                @endif
                            </div>
                            @if($course->year)
                                <p class="small text-muted-theme mb-2">{{ $course->year }}</p>
                            @endif
                            @if($course->localizedDescription())
                                <p class="small text-muted-theme mb-3 flex-grow-1">
                                    {{ \Illuminate\Support\Str::limit($course->localizedDescription(), 140) }}
                                </p>
                            @else
                                <div class="flex-grow-1"></div>
                            @endif
                            <p class="small mb-3">
                                <span class="badge bg-light text-dark border">
                                    {{ __('course_context.your_role', ['role' => $role?->role_name ?? '—']) }}
                                </span>
                            </p>
                            <form method="POST" action="{{ route('courses.select.store') }}">
                                @csrf
                                <input type="hidden" name="course_id" value="{{ $course->course_id }}">
                                @if($intended ?? null)
                                    <input type="hidden" name="intended" value="{{ $intended }}">
                                @endif
                                <button type="submit" class="btn btn-primary w-100">
                                    @if($isCurrent)
                                        <i class="bi bi-check-circle"></i> {{ __('course_context.current_course') }}
                                    @else
                                        <i class="bi bi-box-arrow-in-right"></i> {{ __('course_context.select_title') }}
                                    @endif
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
