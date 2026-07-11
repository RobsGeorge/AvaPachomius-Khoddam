@extends('layouts.app')

@section('title', __('course_applications.open_applications'))

@section('content')
@php
    use App\Models\CourseApplication;
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('course_applications.open_applications') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('course_applications.open_applications_intro') }}</p>
    </div>

    <div class="row g-3">
        @forelse($openForms as $form)
            @php $status = $statuses[$form->course_id] ?? null; @endphp
            <div class="col-md-6 col-lg-4">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">{{ $form->course?->title ?? $form->title }}</h5>
                        @if($status)
                            <span class="badge bg-secondary align-self-start mb-2">{{ __('course_applications.status_'.$status) }}</span>
                        @endif
                        <div class="mt-auto d-grid gap-2">
                            @if($status === CourseApplication::STATUS_NEEDS_CORRECTION)
                                <a href="{{ route('courses.application.edit', $form->course_id) }}" class="btn btn-warning">
                                    {{ __('course_applications.fix_application') }}
                                </a>
                            @elseif(in_array($status, [CourseApplication::STATUS_PENDING_REVIEW, CourseApplication::STATUS_REJECTED], true))
                                <a href="{{ route('courses.application.status', $form->course_id) }}" class="btn btn-outline-primary">
                                    {{ __('course_applications.view_status') }}
                                </a>
                            @else
                                <a href="{{ route('courses.apply', $form->course_id) }}" class="btn btn-primary">
                                    {{ __('course_applications.apply_now') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">{{ __('pages.no_records') }}</div>
            </div>
        @endforelse
    </div>
</div>
@endsection
