@extends('layouts.app')

@section('title', __('pages.graduation_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <a href="{{ route('graduation.index') }}" class="text-muted small">{{ __('pages.graduation_title') }}</a>
            <h1 class="page-title mb-1">{{ __('pages.graduation_course_title') }}</h1>
            <p class="text-muted small mb-0">{{ $course->title }} — {{ $course->year }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('grades.report', $course->course_id) }}" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-table"></i> {{ __('pages.grade_report') }}
            </a>
            <a href="{{ route('grades.admin', $course->course_id) }}" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-gear"></i> {{ __('pages.grading_management') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(! $criteriaConfigured)
        <div class="alert alert-warning d-flex gap-3 align-items-start mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
            <div>
                <strong>{{ __('pages.graduation_criteria_not_set_course') }}</strong>
                <p class="mb-2 mt-1">{{ __('pages.graduation_provisional_warning') }}</p>
                @if(auth()->user()->is_superadmin || auth()->user()->roles->contains('role_name', 'admin'))
                    <a href="{{ route('admin.graduation-settings.index') }}" class="btn btn-sm btn-warning">
                        {{ __('pages.graduation_configure_criteria') }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('pages.graduation_criteria') }}</div>
        <div class="card-body">
            <p class="small text-muted mb-3">{{ __('pages.graduation_criteria_hint') }}</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="small text-muted-theme">{{ __('pages.passing_percentage') }}</div>
                    <div class="fw-semibold fs-5">
                        @if($course->passing_percentage !== null)
                            {{ number_format($course->passing_percentage, 1) }}%
                        @else
                            <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small text-muted-theme">{{ __('pages.min_attendance_percentage') }}</div>
                    <div class="fw-semibold fs-5">
                        @if($course->min_attendance_percentage !== null)
                            {{ number_format($course->min_attendance_percentage, 1) }}%
                        @else
                            <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                        @endif
                    </div>
                </div>
                @if(auth()->user()->is_superadmin || auth()->user()->roles->contains('role_name', 'admin'))
                    <div class="col-md-4 d-flex align-items-end">
                        <a href="{{ route('admin.graduation-settings.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil-square"></i> {{ __('pages.graduation_configure_criteria') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @php
        $eligibleCount = $criteriaConfigured ? $eligible->count() : null;
        $attendanceFail = $criteriaConfigured ? $evaluations->where('failure_reason', 'attendance')->count() : 0;
        $gradeFail = $criteriaConfigured ? $evaluations->where('failure_reason', 'grade')->count() : 0;
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.graduation_eligible_count') }}</div>
                <div class="fs-3 fw-bold text-success">{{ $eligibleCount ?? '—' }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.students') }}</div>
                <div class="fs-3 fw-bold">{{ $evaluations->count() }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.graduation_failed_attendance') }}</div>
                <div class="fs-3 fw-bold text-danger">{{ $attendanceFail }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="app-tile text-center">
                <div class="small text-muted-theme">{{ __('pages.graduation_failed_grade') }}</div>
                <div class="fs-3 fw-bold text-warning">{{ $gradeFail }}</div>
            </div>
        </div>
    </div>

    @if($criteriaConfigured && $eligible->isNotEmpty())
        <div class="app-card card shadow-sm mb-4 border-success">
            <div class="card-header bg-success-subtle fw-semibold text-success">
                <i class="bi bi-mortarboard"></i> {{ __('pages.graduation_eligible_students') }}
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.number') }}</th>
                                <th>{{ __('pages.student') }}</th>
                                <th class="text-center">{{ __('pages.attendance_percentage') }}</th>
                                <th class="text-center">{{ __('pages.total') }}</th>
                                <th class="text-center">{{ __('pages.letter_grade') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($eligible as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['user']->first_name }} {{ $row['user']->second_name }}</div>
                                        <div class="text-muted-theme" style="font-size:0.75rem;">{{ $row['user']->national_id }}</div>
                                    </td>
                                    <td class="text-center">{{ number_format($row['attendance_pct'], 1) }}%</td>
                                    <td class="text-center fw-bold text-success">{{ number_format($row['total_grade'], 1) }}%</td>
                                    <td class="text-center">
                                        <span class="badge bg-success">{{ $row['letter'] }}</span>
                                        <div class="text-muted-theme" style="font-size:0.7rem;">{{ $row['letter_ar'] }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif($criteriaConfigured)
        <div class="alert alert-warning">{{ __('pages.graduation_no_eligible') }}</div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-header fw-semibold">{{ __('pages.graduation_all_students') }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.number') }}</th>
                            <th>{{ __('pages.student') }}</th>
                            <th class="text-center">{{ __('pages.attendance_percentage') }}</th>
                            <th class="text-center">{{ __('pages.total') }}</th>
                            <th class="text-center">{{ __('pages.letter_grade') }}</th>
                            <th class="text-center">{{ __('pages.graduation_eligibility') }}</th>
                            <th>{{ __('pages.graduation_reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($evaluations as $i => $row)
                            <tr class="{{ $row['eligible'] ? 'table-success' : '' }}">
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $row['user']->first_name }} {{ $row['user']->second_name }}</div>
                                    <div class="text-muted-theme" style="font-size:0.75rem;">{{ $row['user']->national_id }}</div>
                                </td>
                                <td class="text-center">
                                    @if($criteriaConfigured)
                                        <span class="{{ $row['attendance_pass'] ? 'text-success' : 'text-danger fw-semibold' }}">
                                            {{ number_format($row['attendance_pct'], 1) }}%
                                        </span>
                                    @else
                                        {{ number_format($row['attendance_pct'], 1) }}%
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-{{ $row['color'] }}">{{ number_format($row['total_grade'], 1) }}%</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $row['color'] }}">{{ $row['letter'] }}</span>
                                    <div class="text-muted-theme" style="font-size:0.7rem;">{{ $row['letter_ar'] }}</div>
                                </td>
                                <td class="text-center">
                                    @if($criteriaConfigured)
                                        @if($row['eligible'])
                                            <span class="badge bg-success">{{ __('pages.graduation_eligible') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('pages.graduation_not_eligible') }}</span>
                                        @endif
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.graduation_not_set_label') }}</span>
                                    @endif
                                </td>
                                <td class="small text-muted-theme">
                                    @if(! $criteriaConfigured)
                                        {{ __('pages.graduation_criteria_not_set_course') }}
                                    @elseif($row['failure_reason'] === 'attendance')
                                        {{ __('pages.graduation_fail_attendance', ['min' => number_format($course->min_attendance_percentage, 0)]) }}
                                    @elseif($row['failure_reason'] === 'grade')
                                        {{ __('pages.graduation_fail_grade', ['min' => number_format($course->passing_percentage, 0)]) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted-theme py-4">{{ __('pages.no_students_registered') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
