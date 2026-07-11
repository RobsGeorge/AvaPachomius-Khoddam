@extends('layouts.app')

@section('title', __('course_applications.waiting_title'))

@section('content')
@php
    use App\Models\CourseApplication;
    use App\Models\CourseApplicationFormField;
    $status = $application?->status ?? CourseApplication::STATUS_PENDING_REVIEW;
    $snapshot = $application?->snapshot ?? [];
    $fieldMap = collect();
    foreach ($form?->steps ?? [] as $step) {
        foreach ($step->fields as $field) {
            $fieldMap[$field->field_key] = $field;
        }
    }
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ $courseModel->title }}</h1>
        @if($status === CourseApplication::STATUS_REJECTED)
            <p class="text-muted-theme mb-0">{{ __('course_applications.rejected_intro') }}</p>
        @elseif($status === CourseApplication::STATUS_NEEDS_CORRECTION)
            <p class="text-muted-theme mb-0">{{ __('course_applications.correction_intro') }}</p>
        @else
            <p class="text-muted-theme mb-0">{{ __('course_applications.waiting_intro') }}</p>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <p class="mb-3">
                <span class="text-muted-theme">{{ __('registration_review.waiting_status_label') }}:</span>
                <span class="badge bg-secondary">{{ __('course_applications.status_'.$status) }}</span>
            </p>

            @if($status === CourseApplication::STATUS_REJECTED && filled($application?->overall_rejection_note))
                <div class="alert alert-danger">
                    <strong>{{ __('course_applications.overall_rejection_note') }}:</strong>
                    {{ $application->overall_rejection_note }}
                </div>
            @endif

            @if($status === CourseApplication::STATUS_NEEDS_CORRECTION)
                <a href="{{ route('courses.application.edit', $courseModel->course_id) }}" class="btn btn-primary">
                    {{ __('course_applications.fix_application') }}
                </a>
            @endif
        </div>
    </div>

    @if($snapshot !== [])
        <div class="app-card card shadow-sm">
            <div class="card-header fw-semibold">{{ __('course_applications.review_title') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($snapshot as $key => $value)
                        @php $field = $fieldMap[$key] ?? null; @endphp
                        <div class="col-md-6">
                            <div class="small text-muted-theme">{{ $field?->label ?? $key }}</div>
                            @if($field?->type === CourseApplicationFormField::TYPE_IMAGE && filled($value))
                                <img src="{{ asset('storage/'.$value) }}" alt="" class="rounded mt-1" style="max-height:120px;">
                            @elseif(is_array($value))
                                <div class="fw-semibold">{{ implode(', ', $value) ?: '—' }}</div>
                            @elseif(is_bool($value))
                                <div class="fw-semibold">{{ $value ? __('course_applications.yes') : __('course_applications.no') }}</div>
                            @else
                                <div class="fw-semibold">{{ $value ?: '—' }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
