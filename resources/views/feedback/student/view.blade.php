@extends('layouts.app')

@section('title', $survey->title)

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <a href="{{ route('feedback.index') }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>

    <h1 class="page-title mb-1">{{ $survey->title }}</h1>
    <p class="text-muted-theme mb-3">{{ $survey->course?->title }} — {{ $survey->module?->title }}</p>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="alert alert-secondary">
        <i class="bi bi-check-circle"></i> {{ __('pages.feedback_submitted_readonly') }}
    </div>

    <div class="app-card card">
        <div class="card-body p-4">
            @foreach($survey->questions as $question)
                <x-feedback-question-field
                    :question="$question"
                    :value="$answersByQuestion->get($question->question_id)?->displayValue()"
                    :readonly="true" />
            @endforeach
            <p class="small text-muted mb-0">{{ __('pages.submitted_on', ['date' => $submission->submitted_at->format('Y-m-d H:i')]) }}</p>
        </div>
    </div>
</div>
@endsection
