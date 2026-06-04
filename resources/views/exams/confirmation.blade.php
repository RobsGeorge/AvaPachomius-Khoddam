@extends('layouts.app')

@section('title', __('exams.confirmation_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:640px;">
    <div class="app-card card shadow-sm text-center">
        <div class="card-body py-5">
            @if($result && $result->isCheater())
                <div class="display-4 text-danger mb-3"><i class="bi bi-shield-exclamation"></i></div>
                <h1 class="h3 mb-3 text-danger">{{ __('exams.cheater_confirmation_title') }}</h1>
                <p class="text-muted mb-4">{{ __('exams.cheater_confirmation_body') }}</p>
            @else
                <div class="display-4 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
                <h1 class="h3 mb-3">{{ __('exams.confirmation_title') }}</h1>
                <p class="text-muted mb-4">{{ __('exams.confirmation_body') }}</p>
            @endif

            @if($result)
                <div class="alert alert-light border mb-4">
                    <div class="fw-semibold">{{ __('exams.your_score') }}</div>
                    @if($result->isCheater())
                        <div class="fs-2 fw-bold text-danger">0%</div>
                        <small class="text-muted">{{ __('exams.instructor_review_cheater') }}</small>
                    @elseif($result->score !== null && $result->status === \App\Models\ExamResult::STATUS_GRADED)
                        <div class="fs-2 fw-bold text-primary">{{ number_format($result->score, 1) }}%</div>
                        @if($schedule->exam->passing_score)
                            <small class="text-muted">{{ __('exams.passing_score') }}: {{ $schedule->exam->passing_score }}%</small>
                        @endif
                    @else
                        <div class="text-muted">{{ __('exams.score_pending') }}</div>
                    @endif
                </div>
            @endif

            <a href="{{ route('exams.index') }}" class="btn btn-primary">
                <i class="bi bi-arrow-right"></i> {{ __('exams.back_to_exams') }}
            </a>
        </div>
    </div>
</div>
@endsection
