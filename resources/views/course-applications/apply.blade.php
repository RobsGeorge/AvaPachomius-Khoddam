@extends('layouts.app')

@section('title', __('course_applications.apply_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('course_applications.apply_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ $courseModel->title }} — {{ $form->description ?: __('course_applications.apply_intro') }}</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="mb-3">
        <div class="progress" style="height: 8px;">
            <div class="progress-bar" style="width: {{ $steps->count() ? (($stepIndex + 1) / $steps->count()) * 100 : 100 }}%"></div>
        </div>
        <div class="small text-muted-theme mt-1">
            {{ __('course_applications.step_of', ['current' => $stepIndex + 1, 'total' => max(1, $steps->count())]) }}
            @if($currentStep) — {{ $currentStep->title }} @endif
        </div>
    </div>

    <form method="POST" action="{{ route('courses.apply.store', $courseModel->course_id) }}" enctype="multipart/form-data">
        @csrf

        @foreach($steps as $index => $step)
            <div class="{{ $index === $stepIndex ? '' : 'd-none' }}">
                @if($step->description)
                    <p class="text-muted-theme">{{ $step->description }}</p>
                @endif
                <div class="row g-3 mb-4">
                    @foreach($step->fields as $field)
                        @include('course-applications.partials.field', [
                            'field' => $field,
                            'snapshot' => $latest?->snapshot ?? [],
                            'rejectedFields' => [],
                            'rejectedComments' => [],
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-between gap-2">
            @if($stepIndex > 0)
                <a href="{{ route('courses.apply', ['course' => $courseModel->course_id, 'step' => $stepIndex - 1]) }}"
                   class="btn btn-outline-secondary">{{ __('course_applications.prev_step') }}</a>
            @else
                <span></span>
            @endif

            @if($stepIndex < $steps->count() - 1)
                <a href="{{ route('courses.apply', ['course' => $courseModel->course_id, 'step' => $stepIndex + 1]) }}"
                   class="btn btn-primary">{{ __('course_applications.next_step') }}</a>
            @else
                <button type="submit" class="btn btn-success">{{ __('course_applications.submit_application') }}</button>
            @endif
        </div>
    </form>
</div>
@endsection
