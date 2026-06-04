@extends('layouts.app')

@section('title', __('pages.exams_schedule_results'))

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="page-title mb-2">{{ __('pages.exams_schedule_results') }}</h2>
            <p class="text-muted small mb-0">{{ __('exams.tip_timer') }} · {{ __('exams.one_attempt_only') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.exam_name') }}</th>
                            <th>{{ __('pages.module') }}</th>
                            <th>{{ __('exams.delivery_mode') }}</th>
                            <th>{{ __('pages.duration') }}</th>
                            <th>{{ __('pages.exam_date') }}</th>
                            <th>{{ __('pages.done') }}?</th>
                            <th>{{ __('pages.my_grades') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $exam->exam_name }}</div>
                                        <small class="text-muted">{{ __('exams.type_' . ($exam->exam_type ?? 'exam')) }}</small>
                                    </td>
                                    <td>{{ $exam->module->title ?? '—' }}</td>
                                    <td>
                                        <span class="badge {{ $online ? 'bg-primary' : 'bg-secondary' }}">
                                            {{ $online ? __('exams.mode_online') : __('exams.mode_offline') }}
                                        </span>
                                    </td>
                                    <td>{{ $exam->duration_minutes }} {{ __('pages.minutes') }}</td>
                                    <td>
                                        {{ $schedule->scheduled_date->format('Y-m-d H:i') }}
                                        @if($online)
                                            <div class="small text-muted">{{ __('exams.timer_label') }} → {{ $schedule->endsAt()->format('H:i') }}</div>
                                        @endif
                                    </td>
                                    <td>
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
                                    </td>
                                    <td>
                                        @if($cheater)
                                            <span class="text-danger small">{{ __('exams.cheater_score_label') }}</span>
                                        @elseif($result && $result->score !== null && ! $result->isCheater())
                                            {{ number_format($result->score, 1) }}%
                                        @elseif($done)
                                            {{ __('exams.score_pending') }}
                                        @elseif(! $online)
                                            <span class="text-muted small">{{ __('exams.grade_by_instructor') }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if($online && $inProgress)
                                            <a href="{{ route('exams.attempt.show', $schedule->schedule_id) }}"
                                               class="btn btn-sm btn-warning">
                                                <i class="bi bi-arrow-repeat"></i> {{ __('exams.continue_exam') }}
                                            </a>
                                        @elseif($online && $canLobby)
                                            <a href="{{ route('exams.attempt.lobby', $schedule->schedule_id) }}"
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-clipboard-check"></i> {{ __('exams.pre_exam_checklist') }}
                                            </a>
                                        @elseif($done || $cheater)
                                            <a href="{{ route('exams.attempt.confirmation', $schedule->schedule_id) }}"
                                               class="btn btn-sm btn-outline-theme">
                                                {{ __('exams.confirmation_title') }}
                                            </a>
                                        @elseif(! $online)
                                            <span class="text-muted small">{{ __('exams.no_online_entry') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-muted small">{{ $exam->exam_name }} — {{ __('pages.no_exams_yet') }}</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted-theme py-4">{{ __('pages.no_exams_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
