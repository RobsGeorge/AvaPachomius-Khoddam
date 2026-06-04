@extends('layouts.app')

@section('title', __('exams.grades_dashboard'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('exams.dashboard') }}" class="text-muted small">{{ __('pages.exams_management') }}</a>
            <h1 class="page-title mb-0">{{ __('exams.grades_dashboard') }}: {{ $exam->exam_name }}</h1>
            <p class="text-muted small mb-0">
                {{ $exam->isOnline() ? __('exams.online_auto_graded') : __('exams.offline_grade_entry') }}
                · {{ __('exams.total_points') }}: {{ $exam->total_points }}
            </p>
        </div>
        @if($exam->isOnline())
            <a href="{{ route('exams.builder', $exam) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square"></i> {{ __('exams.design_exam') }}
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @foreach($exam->schedules as $schedule)
        <div class="app-card card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</span>
                @if($schedule->is_completed)
                    <span class="badge bg-success">{{ __('pages.done') }}</span>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('exams.student') }}</th>
                                <th>{{ __('exams.auto_score') }}</th>
                                <th>{{ __('exams.manual_score') }}</th>
                                <th>{{ __('exams.final_score') }}</th>
                                <th>{{ __('pages.status') }}</th>
                                <th>{{ __('exams.proctor_flags') }}</th>
                                <th>{{ __('pages.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedule->results as $result)
                                <tr>
                                    <td>{{ $result->user->name ?? $result->user->email ?? '#'.$result->user_id }}</td>
                                    <td>{{ $result->auto_score !== null ? number_format($result->auto_score, 1) : '—' }}</td>
                                    <td>{{ $result->manual_score !== null ? number_format($result->manual_score, 1) : '—' }}</td>
                                    <td class="fw-semibold">{{ $result->score !== null ? number_format($result->score, 1).'%' : '—' }}</td>
                                    <td>
                                        @if($result->isCheater())
                                            <span class="badge bg-danger">{{ __('exams.status_cheater') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('exams.status_' . ($result->status ?? 'pending')) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($result->attempt && $result->attempt->proctorEvents->isNotEmpty())
                                            <span class="badge bg-warning text-dark" title="{{ $result->attempt->proctorEvents->pluck('event_type')->join(', ') }}">
                                                {{ $result->attempt->proctor_warnings }} {{ __('exams.warnings') }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if($result->isCheater())
                                            <form method="POST" action="{{ route('exams.grades.clear-cheater', [$exam, $result]) }}" class="d-flex gap-1 flex-wrap">
                                                @csrf
                                                <input type="number" name="score" class="form-control form-control-sm" style="width:80px;"
                                                       min="0" max="100" step="0.1" placeholder="%" title="{{ __('exams.override_score') }}">
                                                <button class="btn btn-sm btn-warning">{{ __('exams.clear_cheater_flag') }}</button>
                                            </form>
                                        @elseif($exam->isOffline())
                                            <form method="POST" action="{{ route('exams.grades.update', [$exam, $result]) }}" class="d-flex gap-1">
                                                @csrf @method('PUT')
                                                <input type="number" name="score" class="form-control form-control-sm" style="width:80px;"
                                                       min="0" max="100" step="0.1" value="{{ $result->score }}" required>
                                                <button class="btn btn-sm btn-primary">{{ __('pages.save') }}</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-theme"
                                                    data-bs-toggle="collapse" data-bs-target="#result-{{ $result->result_id }}">
                                                {{ __('exams.review_essay') }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                                @if($exam->isOnline() && $result->attempt)
                                    <tr class="collapse" id="result-{{ $result->result_id }}">
                                        <td colspan="7" class="bg-light-subtle">
                                            @if($result->attempt->proctorEvents->isNotEmpty())
                                                <div class="mb-3 p-2 border rounded bg-white">
                                                    <div class="fw-semibold small">{{ __('exams.proctor_log') }}</div>
                                                    <ul class="small mb-0">
                                                        @foreach($result->attempt->proctorEvents as $ev)
                                                            <li>
                                                                {{ $ev->created_at->format('H:i:s') }} —
                                                                {{ $ev->event_type }}
                                                                ({{ __('exams.warning') }} #{{ $ev->warning_number }})
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            @foreach($result->attempt->answers as $answer)
                                                @if($answer->question?->question_type === \App\Models\ExamQuestion::TYPE_ESSAY)
                                                    <div class="mb-3 p-3 border rounded bg-white">
                                                        <div class="fw-semibold small">{{ Str::limit($answer->question->prompt, 120) }}</div>
                                                        <p class="small mb-2" style="white-space:pre-line;">{{ $answer->text_answer }}</p>
                                                        @if($answer->ai_feedback)
                                                            <div class="small text-muted"><strong>{{ __('exams.ai_feedback') }}:</strong> {{ $answer->ai_feedback }}</div>
                                                        @endif
                                                        <form method="POST" action="{{ route('exams.grades.update', [$exam, $result]) }}" class="d-flex gap-2 mt-2">
                                                            @csrf @method('PUT')
                                                            <input type="hidden" name="scores[{{ $answer->question_id }}]" value="">
                                                            <label class="small">{{ __('exams.points') }}</label>
                                                            <input type="number" name="scores[{{ $answer->question_id }}]"
                                                                   class="form-control form-control-sm" style="width:90px;"
                                                                   step="0.25" min="0" max="{{ $answer->question->points }}"
                                                                   value="{{ $answer->manual_score ?? $answer->auto_score ?? 0 }}">
                                                            <button class="btn btn-sm btn-primary">{{ __('pages.save') }}</button>
                                                        </form>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">{{ __('pages.no_students') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($exam->isOffline())
                    <div class="card-footer">
                        <div class="small fw-semibold mb-2">{{ __('exams.offline_grade_entry') }}</div>
                        <form method="POST" action="{{ route('exams.grades.offline', $exam) }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="schedule_id" value="{{ $schedule->schedule_id }}">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('exams.student') }} (user_id)</label>
                                <input type="number" name="user_id" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('exams.final_score') }}</label>
                                <input type="number" name="score" class="form-control form-control-sm" min="0" max="100" step="0.1" required>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-success w-100">{{ __('pages.save') }}</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
