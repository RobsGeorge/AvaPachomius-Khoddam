@extends('layouts.app')

@section('title', __('pages.attendance_report_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="page-header-bar">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-theme btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_dashboard') }}
        </a>
        <div class="page-actions">
            <a href="{{ route('attendance.report') }}?export=pdf" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF
            </a>
            <a href="{{ route('attendance.report') }}?export=excel" class="btn btn-outline-theme btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Excel
            </a>
        </div>
    </div>

    <h1 class="page-title text-center mb-4">{{ __('pages.attendance_report_title') }}</h1>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="app-card card h-100 text-center">
                <div class="card-body py-3">
                    <div class="report-stat-number">{{ $overallStats['total_users'] }}</div>
                    <div class="small text-muted-theme fw-semibold">{{ __('pages.total_students') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card h-100 text-center">
                <div class="card-body py-3">
                    <div class="report-stat-number">{{ $overallStats['total_sessions'] }}</div>
                    <div class="small text-muted-theme fw-semibold">{{ __('pages.total_lectures') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card h-100 text-center">
                <div class="card-body py-3">
                    <div class="report-stat-number report-stat-number--present">{{ $overallStats['total_attended'] }}</div>
                    <div class="small text-muted-theme fw-semibold">{{ __('pages.total_present') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card h-100 text-center">
                <div class="card-body py-3">
                    <div class="report-stat-number report-stat-number--absent">{{ $overallStats['total_absent'] }}</div>
                    <div class="small text-muted-theme fw-semibold">{{ __('pages.total_absent') }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md">
            <div class="app-card card h-100 text-center">
                <div class="card-body py-3">
                    <div class="report-stat-number">{{ number_format($overallStats['average_attendance'], 1) }}%</div>
                    <div class="small text-muted-theme fw-semibold">{{ __('pages.avg_attendance_rate') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body p-0">
            @if($users->count() > 0)
                <div class="table-responsive d-none d-lg-block admin-table-desktop">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.student_name') }}</th>
                                <th>{{ __('pages.phone_number') }}</th>
                                <th>{{ __('pages.total_sessions_count') }}</th>
                                <th>{{ __('pages.present_times') }}</th>
                                <th>{{ __('pages.absent_times') }}</th>
                                <th>{{ __('pages.late_times') }}</th>
                                <th>{{ __('pages.attendance_rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td>
                                        <a href="{{ route('attendance.user', $user->user_id) }}" class="fw-semibold text-decoration-none">
                                            {{ $user->first_name }} {{ $user->second_name }}
                                        </a>
                                    </td>
                                    <td>{{ $user->mobile_number }}</td>
                                    <td>{{ $user->total_sessions }}</td>
                                    <td><span class="fw-bold text-success">{{ $user->attended_sessions }}</span></td>
                                    <td><span class="fw-bold text-danger">{{ $user->absent_sessions }}</span></td>
                                    <td><span class="fw-bold text-warning">{{ $user->late_sessions }}</span></td>
                                    <td>
                                        @php
                                            $percentage = $user->attendance_percentage;
                                            $percentageClass = match (true) {
                                                $percentage >= 90 => 'report-pct--excellent',
                                                $percentage >= 75 => 'report-pct--good',
                                                $percentage >= 60 => 'report-pct--average',
                                                default => 'report-pct--poor',
                                            };
                                        @endphp
                                        <span class="report-pct {{ $percentageClass }}">
                                            {{ number_format($percentage, 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-lg-none admin-data-cards student-data-hub p-3">
                    @foreach($users as $user)
                        @php
                            $percentage = $user->attendance_percentage;
                            $percentageClass = match (true) {
                                $percentage >= 90 => 'report-pct--excellent',
                                $percentage >= 75 => 'report-pct--good',
                                $percentage >= 60 => 'report-pct--average',
                                default => 'report-pct--poor',
                            };
                        @endphp
                        <article class="data-card">
                            <div class="data-card-title">
                                <a href="{{ route('attendance.user', $user->user_id) }}" class="text-decoration-none">
                                    {{ $user->first_name }} {{ $user->second_name }}
                                </a>
                            </div>
                            <dl class="data-meta-list mb-0">
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.phone_number') }}</dt>
                                    <dd>{{ $user->mobile_number }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.total_sessions_count') }}</dt>
                                    <dd>{{ $user->total_sessions }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.present_times') }}</dt>
                                    <dd><span class="fw-bold text-success">{{ $user->attended_sessions }}</span></dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.absent_times') }}</dt>
                                    <dd><span class="fw-bold text-danger">{{ $user->absent_sessions }}</span></dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.late_times') }}</dt>
                                    <dd><span class="fw-bold text-warning">{{ $user->late_sessions }}</span></dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.attendance_rate') }}</dt>
                                    <dd><span class="report-pct {{ $percentageClass }}">{{ number_format($percentage, 1) }}%</span></dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="text-center text-muted-theme py-5">
                    <i class="bi bi-people fs-1 d-block mb-3"></i>
                    <p class="mb-0">{{ __('pages.no_students_registered') }}</p>
                </div>
            @endif
        </div>
    </div>

    @if($users->count() > 0)
        <div class="app-card card shadow-sm">
            <div class="card-header fw-semibold">{{ __('pages.report_summary') }}</div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="h6 fw-semibold mb-3">{{ __('pages.top_5_students') }}</h3>
                        <ul class="list-group list-group-flush">
                            @foreach($users->take(5) as $index => $user)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span>{{ $index + 1 }}. {{ $user->first_name }} {{ $user->second_name }}</span>
                                    <span class="report-summary-badge report-summary-badge--high">{{ number_format($user->attendance_percentage, 1) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 fw-semibold mb-3">{{ __('pages.bottom_5_students') }}</h3>
                        <ul class="list-group list-group-flush">
                            @foreach($users->sortBy('attendance_percentage')->take(5) as $index => $user)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span>{{ $index + 1 }}. {{ $user->first_name }} {{ $user->second_name }}</span>
                                    <span class="report-summary-badge report-summary-badge--low">{{ number_format($user->attendance_percentage, 1) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
