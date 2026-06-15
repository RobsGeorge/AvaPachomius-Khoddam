@extends('layouts.app')

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card shadow-sm">
        <div class="card-body">
            <h1 class="page-title mb-4">{{ __('pages.attendance_record_for') }} — {{ $user->first_name . ' ' . $user->second_name . ' ' . $user->third_name }}</h1>

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
                                    <td>{{ $record->session?->session_title ?? __('pages.unspecified') }}</td>
                                    <td class="text-nowrap">{{ $record->display_session_date ?? __('pages.unspecified') }}</td>
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
                                    <td class="text-nowrap">
                                        @if($record->takenBy)
                                            {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $record->display_attendance_time ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @php
                    $total = $attendanceRecords->count();
                    $present = $attendanceRecords->where('status', 'Present')->count();
                    $absent = $attendanceRecords->where('status', 'Absent')->count();
                    $late = $attendanceRecords->where('status', 'Late')->count();
                    $presentPercentage = $total ? round(($present / $total) * 100) : 0;
                    $monthlyStats = $attendanceRecords->groupBy(function ($record) {
                        return $record->display_session_date
                            ? \Carbon\Carbon::parse($record->display_session_date)->format('Y-m')
                            : 'unknown';
                    })->map(function ($records) {
                        return [
                            'total'   => $records->count(),
                            'present' => $records->where('status', 'Present')->count(),
                        ];
                    })->sortKeysDesc();
                @endphp

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="app-card card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.attendance_stats') }}</h3>
                                <p class="small text-muted-theme mb-2">{{ __('pages.attendance_rate') }}</p>
                                <div class="progress mb-2" style="height:1rem;">
                                    <div class="progress-bar bg-success" style="width: {{ $presentPercentage }}%"></div>
                                </div>
                                <p class="mb-3">{{ $presentPercentage }}%</p>
                                <div class="row g-2 text-center">
                                    <div class="col-4">
                                        <div class="fs-4 fw-bold text-success">{{ $present }}</div>
                                        <div class="small text-muted-theme">{{ __('pages.present') }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="fs-4 fw-bold text-danger">{{ $absent }}</div>
                                        <div class="small text-muted-theme">{{ __('pages.absent') }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="fs-4 fw-bold text-warning">{{ $late }}</div>
                                        <div class="small text-muted-theme">{{ __('pages.late') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="app-card card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.monthly_stats') }}</h3>
                                @foreach($monthlyStats as $month => $stat)
                                    <div class="border-bottom pb-2 mb-2">
                                        <div class="fw-semibold">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</div>
                                        <div class="d-flex justify-content-between small text-muted-theme">
                                            <span>{{ __('pages.attendance_label') }} {{ $stat['present'] }}/{{ $stat['total'] }}</span>
                                            <span>{{ $stat['total'] ? round(($stat['present'] / $stat['total']) * 100) : 0 }}%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@include('attendance.partials.status-scripts')
@endsection
