@extends('layouts.app')

@section('title', $survey->title)

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <a href="{{ route('feedback.index') }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>

    <h1 class="page-title mb-1">{{ $survey->title }}</h1>
    <p class="text-muted-theme mb-3">{{ $survey->course?->title }} — {{ $survey->module?->title }}</p>

    <div class="alert alert-info">
        <i class="bi bi-shield-lock"></i> {{ __('pages.feedback_anonymous_notice') }}
    </div>

    @if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('feedback.surveys.submit', $survey) }}">
                @csrf
                @foreach($survey->questions as $question)
                    <x-feedback-question-field :question="$question" />
                @endforeach
                <button type="submit" class="btn btn-primary w-100">{{ __('pages.submit_feedback') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
