@extends('layouts.app')

@section('title', __('pages.feedback_manage_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.feedback_manage_title') }}</h1>
        <a href="{{ route('feedback.surveys.create') }}" class="btn btn-primary">{{ __('pages.feedback_create_survey') }}</a>
    </div>

@forelse($surveys as $survey)
        <div class="app-card card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h5 class="mb-1">{{ $survey->title }}</h5>
                    <small class="text-muted-theme">{{ $survey->course?->title }} — {{ $survey->module?->title }}</small><br>
                    <span class="badge bg-{{ $survey->status === 'open' ? 'success' : ($survey->status === 'draft' ? 'secondary' : 'dark') }}">{{ __('pages.feedback_status_'.$survey->status) }}</span>
                    <span class="badge bg-light text-dark">{{ $survey->submissions_count }} {{ __('pages.responses') }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('feedback.surveys.edit', $survey) }}" class="btn btn-outline-theme btn-sm">{{ __('pages.edit') }}</a>
                    @if($survey->submissions_count > 0)
                        <a href="{{ route('feedback.surveys.report', $survey) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_report') }}</a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted-theme">{{ __('pages.feedback_no_surveys') }}</p>
    @endforelse
</div>
@endsection
