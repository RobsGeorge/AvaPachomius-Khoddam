@extends('layouts.app')

@section('title', __('pages.exams_schedule_results'))

@section('content')
<div class="container py-4 animate-in exams-hub">
    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="page-title mb-2">{{ __('pages.exams_schedule_results') }}</h2>
            <p class="text-muted small mb-0">{{ __('exams.tip_timer') }} · {{ __('exams.one_attempt_only') }}</p>
        </div>
    </div>

<div class="row g-3">
        @forelse($exams as $exam)
            @forelse($exam->schedules as $schedule)
                @php
                    $result = $exam->results->firstWhere('schedule_id', $schedule->schedule_id);
                    $attempt = $exam->attempts->firstWhere('schedule_id', $schedule->schedule_id);
                    $done = $result && $result->isDone();
                    $cheater = $result && $result->isCheater();
                    $online = $exam->isOnline();
                    $started = $schedule->hasStarted();
                    $ended = $schedule->hasEnded();
                    $inProgress = $attempt && $attempt->hasStartedAttempt() && ! $attempt->isSubmitted();
                    $canLobby = $online && $started && ! $ended && ! $done && ! $inProgress;
                @endphp
                <div class="col-12 col-lg-6">
                    <div class="app-card card shadow-sm h-100 exam-schedule-card">
                        <div class="card-body d-flex flex-column gap-3">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $exam->exam_name }}</h5>
                                <small class="text-muted">{{ __('exams.type_' . ($exam->exam_type ?? 'exam')) }}</small>
                            </div>

                            <dl class="exam-meta-list mb-0">
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.module') }}</dt>
                                    <dd>{{ $exam->module->title ?? '—' }}</dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('exams.delivery_mode') }}</dt>
                                    <dd>
                                        <span class="badge {{ $online ? 'bg-primary' : 'bg-secondary' }}">
                                            {{ $online ? __('exams.mode_online') : __('exams.mode_offline') }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.duration') }}</dt>
                                    <dd>{{ $exam->duration_minutes }} {{ __('pages.minutes') }}</dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.exam_date') }}</dt>
                                    <dd>
                                        {{ $schedule->scheduled_date->format('Y-m-d H:i') }}
                                        @if($online)
                                            <div class="small text-muted">{{ __('exams.timer_label') }} → {{ $schedule->endsAt()->format('H:i') }}</div>
                                        @endif
                                    </dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.done') }}?</dt>
                                    <dd>
                                        @if($cheater)
                                            <span class="badge bg-danger">{{ __('exams.status_cheater') }}</span>
                                        @elseif($done)
                                            <span class="badge bg-success">{{ __('pages.done') }}</span>
                                        @elseif($ended && $online)
                                            <span class="badge bg-secondary">{{ __('exams.exam_ended') }}</span>
                                        @elseif(! $started && $online)
                                            <span class="badge bg-info text-dark">{{ __('exams.waiting_to_start', ['time' => $schedule->scheduled_date->format('H:i')]) }}</span>
                                        @elseif($inProgress)
                                            <span class="badge bg-warning text-dark">{{ __('exams.in_progress') }}</span>
                                        @elseif(! $online)
                                            <span class="badge bg-light text-dark border">{{ __('exams.offline_pending_grade') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('pages.not_done') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.my_grades') }}</dt>
                                    <dd>
                                        @if($cheater)
                                            <span class="text-danger small">{{ __('exams.cheater_score_label') }}</span>
                                        @elseif($result && $result->score !== null && ! $result->isCheater())
                                            <span class="fw-semibold">{{ number_format($result->score, 1) }}%</span>
                                        @elseif($done)
                                            {{ __('exams.score_pending') }}
                                        @elseif(! $online)
                                            <span class="text-muted small">{{ __('exams.grade_by_instructor') }}</span>
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-auto pt-2 border-top">
                                @if($online && $inProgress)
                                    <a href="{{ route('exams.attempt.show', $schedule->schedule_id) }}"
                                       class="btn btn-warning w-100">
                                        <i class="bi bi-arrow-repeat"></i> {{ __('exams.continue_exam') }}
                                    </a>
                                @elseif($online && $canLobby)
                                    <a href="{{ route('exams.attempt.lobby', $schedule->schedule_id) }}"
                                       class="btn btn-primary w-100">
                                        <i class="bi bi-clipboard-check"></i> {{ __('exams.pre_exam_checklist') }}
                                    </a>
                                @elseif($done || $cheater)
                                    <a href="{{ route('exams.attempt.confirmation', $schedule->schedule_id) }}"
                                       class="btn btn-outline-theme w-100">
                                        {{ __('exams.confirmation_title') }}
                                    </a>
                                @elseif(! $online)
                                    <span class="text-muted small d-block text-center">{{ __('exams.no_online_entry') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="app-card card shadow-sm">
                        <div class="card-body text-muted small">
                            {{ $exam->exam_name }} — {{ __('pages.no_exams_yet') }}
                        </div>
                    </div>
                </div>
            @endforelse
        @empty
            <div class="col-12">
                <div class="app-tile text-center text-muted-theme py-5">{{ __('pages.no_exams_yet') }}</div>
            </div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
<style>
.exams-hub .exam-meta-list { display: flex; flex-direction: column; gap: 0.65rem; }
.exams-hub .exam-meta-row {
    display: grid;
    grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
    gap: 0.5rem 0.75rem;
    align-items: start;
}
.exams-hub .exam-meta-row dt {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
    word-break: break-word;
}
.exams-hub .exam-meta-row dd {
    margin: 0;
    font-size: 0.95rem;
    word-break: break-word;
}
.exams-hub .exam-schedule-card .btn { white-space: normal; }
</style>
@endpush
