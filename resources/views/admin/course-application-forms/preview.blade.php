@extends('layouts.app')

@section('title', __('course_applications.applicant_view'))

@section('content')
@php
    $progressPercent = $steps->count() ? (($stepIndex + 1) / $steps->count()) * 100 : 100;
@endphp
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('course_applications.applicant_view') }}</h1>
            <p class="text-muted-theme mb-0">{{ $courseModel->title }} — {{ $form->title ?: $courseModel->title }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.courses.application-form.edit', $courseModel->course_id) }}" class="btn btn-outline-secondary btn-sm">
                {{ __('course_applications.manage_form') }}
            </a>
            <a href="{{ route('admin.courses.application-forms.index') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('course_applications.all_courses') }}
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-eye me-1"></i> {{ __('course_applications.applicant_preview_banner') }}
    </div>

    <div class="mb-3">
        <div class="progress" style="height: 8px;">
            <div class="progress-bar" @style(['width' => $progressPercent.'%'])></div>
        </div>
        <div class="small text-muted-theme mt-1">
            {{ __('course_applications.step_of', ['current' => $stepIndex + 1, 'total' => max(1, $steps->count())]) }}
            @if($currentStep) — {{ $currentStep->title }} @endif
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            @foreach($steps as $index => $step)
                <div class="{{ $index === $stepIndex ? '' : 'd-none' }}">
                    @if($step->description)
                        <p class="text-muted-theme">{{ $step->description }}</p>
                    @endif
                    <div class="row g-3">
                        @foreach($step->fields as $field)
                            @include('course-applications.partials.field', [
                                'field' => $field,
                                'snapshot' => [],
                                'rejectedFields' => [],
                                'rejectedComments' => [],
                                'previewMode' => true,
                            ])
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="d-flex justify-content-between gap-2">
        @if($stepIndex > 0)
            <a href="{{ route('admin.courses.application-form.preview', ['course' => $courseModel->course_id, 'step' => $stepIndex - 1]) }}"
               class="btn btn-outline-secondary">{{ __('course_applications.prev_step') }}</a>
        @else
            <span></span>
        @endif

        @if($stepIndex < $steps->count() - 1)
            <a href="{{ route('admin.courses.application-form.preview', ['course' => $courseModel->course_id, 'step' => $stepIndex + 1]) }}"
               class="btn btn-primary">{{ __('course_applications.next_step') }}</a>
        @else
            <button type="button" class="btn btn-success" disabled>{{ __('course_applications.submit_application') }}</button>
        @endif
    </div>
</div>
@endsection
