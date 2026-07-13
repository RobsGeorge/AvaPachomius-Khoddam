@extends('layouts.app')

@section('title', __('course_graduation.closing_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('graduation.show', $course->course_id) }}" class="text-muted small">{{ __('pages.graduation_title') }}</a>
            <h1 class="page-title mb-1">{{ __('course_graduation.closing_title') }}</h1>
            <p class="text-muted small mb-0">{{ __('course_graduation.closing_subtitle', ['course' => $course->title.' — '.$course->year]) }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('courses.certificate-template.edit', $course->course_id) }}" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-award"></i> {{ __('course_graduation.certificate_template') }}
            </a>
            <a href="{{ route('graduation.show', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">
                {{ __('pages.graduation_title') }}
            </a>
        </div>
    </div>

@php
        $statusLabel = match($course->status) {
            'grading_locked' => __('course_graduation.status_grading_locked'),
            'announced' => __('course_graduation.status_announced'),
            'closed' => __('course_graduation.status_closed'),
            'archived' => __('course_graduation.status_archived'),
            default => __('course_graduation.status_active'),
        };
    @endphp

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span>{{ __('course_graduation.checklist') }}</span>
            <span class="badge bg-secondary">{{ $statusLabel }}</span>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                <li class="mb-2">
                    <i class="bi {{ $checklist['criteria_configured'] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-danger' }}"></i>
                    {{ $checklist['criteria_configured'] ? __('course_graduation.criteria_configured') : __('course_graduation.criteria_missing') }}
                </li>
                <li class="mb-2">
                    <i class="bi bi-people"></i>
                    {{ __('course_graduation.student_count', ['count' => $checklist['student_count']]) }}
                </li>
                @if($checklist['ungraded_items'] > 0)
                    <li class="mb-2 text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ __('course_graduation.ungraded_items', ['count' => $checklist['ungraded_items']]) }}
                    </li>
                @endif
                @if($checklist['open_attendance_sessions'] > 0)
                    <li class="mb-2 text-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        {{ __('course_graduation.open_attendance', ['count' => $checklist['open_attendance_sessions']]) }}
                    </li>
                @endif
                <li>
                    <i class="bi bi-mortarboard"></i>
                    {{ __('course_graduation.eligible_count', ['count' => $checklist['eligible_count']]) }}
                </li>
            </ul>
        </div>
    </div>

    @if($checklist['can_lock'])
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h6 fw-semibold">{{ __('course_graduation.lock_grading') }}</h2>
                <p class="small text-muted mb-3">{{ __('course_graduation.lock_grading_hint') }}</p>
                <form method="POST" action="{{ route('courses.closing.lock', $course->course_id) }}" onsubmit="return confirm(@json(__('course_graduation.lock_grading_confirm')))">
                    @csrf
                    <button type="submit" class="btn btn-warning">{{ __('course_graduation.lock_grading') }}</button>
                </form>
            </div>
        </div>
    @endif

    @if(in_array($course->status, ['grading_locked', 'announced', 'closed'], true))
        <div class="app-card card shadow-sm mb-4">
            <div class="card-header fw-semibold">{{ __('course_graduation.grace_settings') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('courses.closing.grace', $course->course_id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" name="grace_marks_enabled" value="1" class="form-check-input" id="grace_enabled"
                                    {{ old('grace_marks_enabled', $course->grace_marks_enabled) ? 'checked' : '' }}
                                    {{ $course->status !== 'grading_locked' ? 'disabled' : '' }}>
                                <label class="form-check-label" for="grace_enabled">{{ __('course_graduation.grace_enabled') }}</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('course_graduation.max_grace_marks') }}</label>
                            <input type="number" step="0.1" min="0" max="100" name="max_grace_marks" class="form-control"
                                   value="{{ old('max_grace_marks', $course->max_grace_marks) }}"
                                   {{ $course->status !== 'grading_locked' ? 'readonly' : '' }}>
                        </div>
                    </div>

                    @if($course->grace_marks_enabled || $course->status === 'grading_locked')
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('pages.student') }}</th>
                                        <th>{{ __('course_graduation.eligible_for_grace') }}</th>
                                        <th>{{ __('course_graduation.pending_grace_marks') }}</th>
                                        <th class="text-center">{{ __('pages.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($preview as $row)
                                        @php $enrollment = $enrollments[$row['user']->user_id] ?? null; @endphp
                                        <tr>
                                            <td>{{ $row['user']->first_name }} {{ $row['user']->second_name }}</td>
                                            <td>
                                                <input type="hidden" name="grace[{{ $row['user']->user_id }}][eligible_for_grace]" value="0">
                                                <input type="checkbox" name="grace[{{ $row['user']->user_id }}][eligible_for_grace]" value="1" class="form-check-input"
                                                    {{ old("grace.{$row['user']->user_id}.eligible_for_grace", $enrollment?->eligible_for_grace) ? 'checked' : '' }}
                                                    {{ $course->status !== 'grading_locked' ? 'disabled' : '' }}>
                                            </td>
                                            <td>
                                                <input type="number" step="0.1" min="0" max="100" class="form-control form-control-sm"
                                                       name="grace[{{ $row['user']->user_id }}][pending_grace_marks]"
                                                       value="{{ old("grace.{$row['user']->user_id}.pending_grace_marks", $enrollment?->pending_grace_marks ?? 0) }}"
                                                       {{ $course->status !== 'grading_locked' ? 'readonly' : '' }}>
                                            </td>
                                            <td class="text-center">
                                                {{ number_format($row['raw_total_grade'] ?? $row['total_grade'], 1) }}
                                                @if(($row['grace_marks_applied'] ?? 0) > 0)
                                                    <span class="text-success">+{{ number_format($row['grace_marks_applied'], 1) }}</span>
                                                    = {{ number_format($row['total_grade'], 1) }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if($course->status === 'grading_locked')
                        <button type="submit" class="btn btn-primary">{{ __('course_graduation.save_grace') }}</button>
                    @endif
                </form>
            </div>
        </div>
    @endif

    @if($preview->isNotEmpty())
        <div class="app-card card shadow-sm mb-4">
            <div class="card-header fw-semibold">{{ __('course_graduation.preview_title') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.student') }}</th>
                                <th class="text-center">{{ __('pages.attendance_percentage') }}</th>
                                <th class="text-center">{{ __('course_graduation.raw_grade') }}</th>
                                <th class="text-center">{{ __('course_graduation.grace_applied') }}</th>
                                <th class="text-center">{{ __('course_graduation.final_grade') }}</th>
                                <th class="text-center">{{ __('pages.graduation_eligibility') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($preview as $row)
                                <tr class="{{ ($row['graduated'] ?? $row['eligible'] ?? false) ? 'table-success' : '' }}">
                                    <td>{{ $row['user']->first_name }} {{ $row['user']->second_name }}</td>
                                    <td class="text-center">{{ number_format($row['attendance_pct'], 1) }}%</td>
                                    <td class="text-center">{{ number_format($row['raw_total_grade'] ?? $row['total_grade'], 1) }}</td>
                                    <td class="text-center">{{ number_format($row['grace_marks_applied'] ?? 0, 1) }}</td>
                                    <td class="text-center fw-bold">{{ number_format($row['total_grade'], 1) }}</td>
                                    <td class="text-center">
                                        @if($row['graduated'] ?? $row['eligible'] ?? false)
                                            <span class="badge bg-success">{{ __('course_graduation.graduated') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('course_graduation.not_graduated') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($checklist['can_announce'])
        <div class="app-card card shadow-sm mb-4 border-success">
            <div class="card-body">
                <form method="POST" action="{{ route('courses.closing.announce', $course->course_id) }}" onsubmit="return confirm(@json(__('course_graduation.announce_confirm')))">
                    @csrf
                    <button type="submit" class="btn btn-success">{{ __('course_graduation.announce_grades') }}</button>
                </form>
            </div>
        </div>
    @endif

    @if($checklist['can_close'])
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('courses.closing.close', $course->course_id) }}" onsubmit="return confirm(@json(__('course_graduation.close_confirm')))">
                    @csrf
                    <div class="form-check mb-3">
                        <input type="checkbox" name="archive_staff" value="1" class="form-check-input" id="archive_staff" checked>
                        <label class="form-check-label" for="archive_staff">{{ __('course_graduation.archive_staff') }}</label>
                    </div>
                    <button type="submit" class="btn btn-danger">{{ __('course_graduation.close_course') }}</button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
