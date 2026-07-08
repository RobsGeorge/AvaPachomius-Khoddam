@extends('layouts.app')

@section('title', __('pages.feedback_hub_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <h1 class="page-title mb-4">{{ __('pages.feedback_hub_title') }}</h1>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

    <p class="text-muted-theme mb-4">{{ __('pages.feedback_hub_intro') }}</p>

    @forelse($surveys as $survey)
        @php
            $submitted = $survey->submissions->isNotEmpty();
            $open = $survey->isOpen();
            $status = $submitted ? 'submitted' : ($open ? 'open' : 'closed');
        @endphp
        <div class="app-card card mb-3">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-1">{{ $survey->title }}</h5>
                    <small class="text-muted-theme d-block">
                        {{ $survey->course?->title }} — {{ $survey->module?->title }}
                    </small>
                    @if($survey->due_at)
                        <small class="text-muted-theme">{{ __('pages.due') }}: {{ $survey->due_at->format('Y-m-d H:i') }}</small>
                    @endif
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if($status === 'submitted')
                        <span class="badge bg-success">{{ __('pages.feedback_submitted') }}</span>
                        <a href="{{ route('feedback.surveys.show', $survey) }}" class="btn btn-outline-theme btn-sm">{{ __('pages.view_feedback') }}</a>
                    @elseif($status === 'open')
                        <span class="badge bg-warning text-dark">{{ __('pages.feedback_open') }}</span>
                        <a href="{{ route('feedback.surveys.show', $survey) }}" class="btn btn-primary btn-sm">{{ __('pages.answer_feedback') }}</a>
                    @elseif($status === 'closed')
                        <span class="badge bg-secondary">{{ __('pages.feedback_closed') }}</span>
                        @if($submitted)
                            <a href="{{ route('feedback.surveys.show', $survey) }}" class="btn btn-outline-theme btn-sm">{{ __('pages.view_feedback') }}</a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="app-tile text-center text-muted-theme py-5">{{ __('pages.feedback_none_available') }}</div>
    @endforelse
</div>
@endsection
