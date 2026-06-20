@extends('layouts.app')

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card shadow-sm">
        <div class="card-body">
            <h1 class="page-title mb-4">{{ __('pages.attendance_on_date', ['date' => $date]) }}</h1>

            @if($attendanceRecords->count() === 0)
                <p>{{ __('pages.no_attendance_records') }}.</p>
            @else
                <div class="table-responsive mb-4">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-nowrap">{{ __('pages.server_name') }}</th>
                                <th class="text-nowrap">{{ __('pages.date') }}</th>
                                <th class="text-nowrap">{{ __('pages.status') }}</th>
                                <th class="text-nowrap">{{ __('pages.recorded_by') }}</th>
                                <th class="text-nowrap">{{ __('pages.recorded_at') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendanceRecords as $record)
                                <tr>
                                    <td class="text-nowrap">
                                        @if($record->user)
                                            <a href="{{ route('attendance.user', $record->user_id) }}">
                                                {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
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
                                        <div id="permission-reason-{{ $record->attendance_id }}" class="mt-1 {{ $record->status === 'Permission' ? '' : 'd-none' }}">
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
                @endphp

                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="app-card card h-100">
                            <div class="card-body">
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.today_stats') }}</h3>
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
                                <h3 class="h6 fw-semibold mb-3">{{ __('pages.session_stats') }}</h3>
                                @foreach($sessionStats as $stat)
                                    @php $pct = $stat->total_records ? ($stat->present_count / $stat->total_records) * 100 : 0; @endphp
                                    <div class="border-bottom pb-3 mb-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                                            <div>
                                                <div class="fw-semibold">{{ $stat->session_title }}</div>
                                                <div class="small text-muted-theme">{{ __('pages.present_of_total', ['present' => $stat->present_count, 'total' => $stat->total_records]) }}</div>
                                            </div>
                                            <div class="fw-semibold">{{ number_format($pct, 1) }}%</div>
                                        </div>
                                        <div class="progress" style="height:0.5rem;">
                                            <div class="progress-bar" style="width: {{ $pct }}%"></div>
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
