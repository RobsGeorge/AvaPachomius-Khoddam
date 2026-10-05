@extends('layouts.app')

@section('title', $announcement->title)

@section('content')
<div class="container py-4 animate-in" style="max-width:760px;">
    <a href="{{ route('announcements.index') }}" class="text-decoration-none">&larr; {{ __('announcements.title') }}</a>

    <article class="app-card card shadow-sm mt-3">
        <div class="card-body">
            <h1 class="page-title h3 mb-2">{{ $announcement->title }}</h1>
            <p class="text-muted-theme small mb-4">
                {{ $announcement->published_at?->format('d/m/Y H:i') }}
                @if($announcement->course) · {{ $announcement->course->title }} @endif
            </p>
            <div class="announcement-body">{!! nl2br(e($announcement->body)) !!}</div>
            @php $linkedSurvey = $survey ?? $announcement->linkedFeedbackSurvey(); @endphp
            @if($linkedSurvey)
                <a href="{{ route('feedback.surveys.show', $linkedSurvey) }}"
                   class="announcement-home-card text-decoration-none d-block mt-4">
                    <strong class="d-block mb-1">{{ $linkedSurvey->title }}</strong>
                    <span class="text-muted-theme small d-block mb-2">
                        {{ $linkedSurvey->course?->title }} — {{ $linkedSurvey->scopeLabel() }}
                    </span>
                    <span class="btn btn-primary btn-sm">{{ __('announcements.open_survey') }}</span>
                </a>
            @endif
        </div>
    </article>
</div>
@endsection
