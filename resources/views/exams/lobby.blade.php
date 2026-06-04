@extends('layouts.app')

@section('title', __('exams.pre_exam_checklist'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <a href="{{ route('exams.index') }}" class="text-muted small">{{ __('exams.back_to_exams') }}</a>

    <div class="app-card card shadow-sm mt-3">
        <div class="card-header fw-semibold">
            <i class="bi bi-clipboard-check"></i> {{ __('exams.pre_exam_checklist') }}
        </div>
        <div class="card-body">
            <h1 class="h4 mb-1">{{ $exam->exam_name }}</h1>
            <p class="text-muted small mb-4">{{ $exam->module->title ?? '' }}</p>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light-subtle h-100">
                        <div class="small text-muted">{{ __('pages.exam_date') }}</div>
                        <div class="fw-semibold">{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 border rounded bg-light-subtle h-100">
                        <div class="small text-muted">{{ __('exams.timer_label') }}</div>
                        <div class="fw-semibold">{{ $schedule->endsAt()->format('Y-m-d H:i') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded bg-light-subtle h-100">
                        <div class="small text-muted">{{ __('pages.duration') }}</div>
                        <div class="fw-semibold">{{ $exam->duration_minutes }} {{ __('pages.minutes') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded bg-light-subtle h-100">
                        <div class="small text-muted">{{ __('exams.total_points') }}</div>
                        <div class="fw-semibold">{{ $exam->total_points }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 border rounded bg-light-subtle h-100">
                        <div class="small text-muted">{{ __('pages.lectures') ?? 'Questions' }}</div>
                        <div class="fw-semibold">{{ $exam->questions->count() }}</div>
                    </div>
                </div>
            </div>

            @if($exam->exam_description)
                <div class="alert alert-light border mb-4">
                    <strong>{{ __('exams.instructions') }}:</strong>
                    <div class="mt-1" style="white-space:pre-line;">{{ $exam->exam_description }}</div>
                </div>
            @endif

            @if($exam->passing_score)
                <p class="small text-muted mb-4">{{ __('exams.passing_score') }}: <strong>{{ $exam->passing_score }}%</strong></p>
            @endif

            @if(! $canEnter)
                <div class="alert alert-warning">
                    @if(! $timer['has_started'])
                        {{ __('exams.waiting_to_start', ['time' => $schedule->scheduled_date->format('H:i')]) }}
                    @else
                        {{ __('exams.exam_ended') }}
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('exams.attempt.begin', $schedule->schedule_id) }}">
                    @csrf
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="acknowledge_rules" value="1" id="ackRules" required>
                            <label class="form-check-label" for="ackRules">{{ __('exams.checklist_rules') }}</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="acknowledge_timer" value="1" id="ackTimer" required>
                            <label class="form-check-label" for="ackTimer">{{ __('exams.checklist_timer') }}</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="acknowledge_proctor" value="1" id="ackProctor" required>
                            <label class="form-check-label" for="ackProctor">{{ __('exams.checklist_proctor') }}</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="acknowledge_one_attempt" value="1" id="ackOnce" required>
                            <label class="form-check-label" for="ackOnce">{{ __('exams.checklist_one_attempt') }}</label>
                        </div>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
                    @endif

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-play-fill"></i>
                        {{ ($exam->exam_type ?? 'exam') === 'quiz' ? __('exams.enter_quiz') : __('exams.enter_exam') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
