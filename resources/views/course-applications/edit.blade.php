@extends('layouts.app')

@section('title', __('course_applications.correction_title'))

@section('content')
@php
    $rejectedComments = $application?->fieldReviews
        ->where('status', 'rejected')
        ->keyBy('field_key') ?? collect();
    $snapshot = $application?->snapshot ?? [];
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        <h1 class="page-title mb-1">{{ __('course_applications.correction_title') }}</h1>
        <p class="text-muted-theme mb-0">{{ $courseModel->title }} — {{ __('course_applications.correction_intro') }}</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('courses.application.update', $courseModel->course_id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    @foreach($form->steps as $step)
                        @foreach($step->fields as $field)
                            @include('course-applications.partials.field', [
                                'field' => $field,
                                'snapshot' => $snapshot,
                                'rejectedFields' => $rejectedFields,
                                'rejectedComments' => $rejectedComments,
                            ])
                        @endforeach
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary mt-4">{{ __('course_applications.resubmit') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
