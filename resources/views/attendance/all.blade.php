@extends('layouts.app')

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card shadow-sm">
        <div class="card-body">
            <h1 class="page-title mb-4">{{ __('pages.all_records') }}</h1>

            @if(session('success'))
                <div class="alert alert-success mb-3">{{ session('success') }}</div>
            @endif

            @if($attendanceRecords->count() === 0)
                <p>{{ __('pages.no_attendance_records') }}.</p>
            @else
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">{{ __('pages.user') }}</th>
                                <th class="text-nowrap">{{ __('pages.lecture') }}</th>
                                <th class="text-nowrap">{{ __('pages.date') }}</th>
                                <th class="text-nowrap">{{ __('pages.status') }}</th>
                                <th class="text-nowrap">{{ __('pages.permission_reason') }}</th>
                                <th class="text-nowrap">{{ __('pages.recorded_by') }}</th>
                                <th class="text-nowrap">{{ __('pages.recorded_at') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendanceRecords as $record)
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="{{ route('attendance.user', $record->user_id) }}">
                                            {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                                        </a>
                                    </td>
                                    <td>{{ $record->session->session_title }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('attendance.by-date', $record->session_date) }}">
                                            {{ $record->session_date }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">
                                        <select class="status-select form-select form-select-sm"
                                                data-attendance-id="{{ $record->attendance_id }}"
                                                data-current-status="{{ $record->status }}"
                                                onchange="updateStatus(this)">
                                            <option value="Present" {{ $record->status === 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
                                            <option value="Absent" {{ $record->status === 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
                                            <option value="Late" {{ $record->status === 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
                                            <option value="Permission" {{ $record->status === 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
                                        </select>
                                    </td>
                                    <td style="min-width:140px;">
                                        <div id="permission-reason-{{ $record->attendance_id }}" class="{{ $record->status === 'Permission' ? '' : 'd-none' }}">
                                            <input type="text"
                                                   class="permission-reason form-control form-control-sm"
                                                   placeholder="{{ __('pages.permission_reason') }}"
                                                   value="{{ $record->permission_reason }}"
                                                   onchange="updatePermissionReason(this, {{ $record->attendance_id }})">
                                        </div>
                                    </td>
                                    <td class="text-nowrap">{{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}</td>
                                    <td class="text-nowrap">{{ $record->attendance_time }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="app-card card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.general_stats') }}</h3>
                                <p class="mb-1">{{ __('pages.total_records') }}: <strong>{{ $overallStats->total }}</strong></p>
                                <p class="mb-1 text-success">{{ __('pages.present_plural') }}: <strong>{{ $overallStats->present }}</strong></p>
                                <p class="mb-1 text-danger">{{ __('pages.absent_count') }}: <strong>{{ $overallStats->absent }}</strong></p>
                                <p class="mb-1 text-warning">{{ __('pages.late_count') }}: <strong>{{ $overallStats->late }}</strong></p>
                                <p class="mb-0">{{ __('pages.attendance_rate') }}: <strong>{{ $overallStats->total ? round(($overallStats->present / $overallStats->total) * 100) : 0 }}%</strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-card card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.daily_stats') }}</h3>
                                @foreach($dailyStats as $stat)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="fw-semibold">{{ $stat->date }}</div>
                                        <div class="small text-muted-theme">{{ __('pages.attendance_label') }} {{ $stat->present }} ({{ $stat->total ? round(($stat->present / $stat->total) * 100) : 0 }}%)</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="app-card card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.highest_attendance') }}</h3>
                                @foreach($userStats as $stat)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="fw-semibold">{{ $stat->first_name . ' ' . $stat->second_name }}</div>
                                        <div class="small text-muted-theme">{{ __('pages.attendance_rate') }}: {{ $stat->total ? round(($stat->present / $stat->total) * 100) : 0 }}%</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div>{{ $attendanceRecords->links() }}</div>
            @endif
        </div>
    </div>
</div>

@include('attendance.partials.status-scripts')
@endsection
