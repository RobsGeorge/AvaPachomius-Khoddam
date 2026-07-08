@extends('layouts.app')

@section('title', __('pages.student_response'))

@section('content')
<div class="container py-4 animate-in" style="max-width:820px;">
    <a href="{{ route('feedback.surveys.report', $survey) }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>
    <h1 class="page-title mb-1">{{ $user->displayName() }}</h1>
    <p class="text-muted-theme mb-4">{{ $survey->title }} — {{ $submission->submitted_at?->format('Y-m-d H:i') }}</p>

    <div class="app-card card">
        <div class="card-body p-4">
            @foreach($submission->answers->sortBy(fn($a) => $a->question?->order_index) as $answer)
                <div class="mb-4 pb-3 border-bottom">
                    <div class="fw-semibold">{{ $answer->question?->scopeLabel() }}</div>
                    <div class="mt-2 p-3 bg-light rounded">{{ $answer->displayValue() }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
