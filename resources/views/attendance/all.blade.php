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
                <p class="mb-4">{{ __('pages.no_attendance_records') }}.</p>
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
                                        @if($record->user)
                                            <a href="{{ route('attendance.user', $record->user_id) }}">
                                                {{ trim($record->user->first_name . ' ' . $record->user->second_name . ' ' . ($record->user->third_name ?? '')) }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $record->session?->session_title ?? __('pages.unspecified') }}</td>
                                    <td class="text-nowrap">
                                        @if($record->display_session_date)
                                            <a href="{{ route('attendance.by-date', $record->display_session_date) }}">
                                                {{ $record->display_session_date }}
                                            </a>
                                        @else
                                            {{ __('pages.unspecified') }}
                                        @endif
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

                <div>{{ $attendanceRecords->links() }}</div>
            @endif

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">{{ __('pages.general_stats') }}</h3>
                            <p class="mb-1">{{ __('pages.total_records') }}: <strong>{{ $overallStats->total ?? 0 }}</strong></p>
                            <p class="mb-1 text-success">{{ __('pages.present_plural') }}: <strong>{{ $overallStats->present ?? 0 }}</strong></p>
                            <p class="mb-1 text-danger">{{ __('pages.absent_count') }}: <strong>{{ $overallStats->absent ?? 0 }}</strong></p>
                            <p class="mb-1 text-warning">{{ __('pages.late_count') }}: <strong>{{ $overallStats->late ?? 0 }}</strong></p>
                            <p class="mb-0">{{ __('pages.attendance_rate') }}: <strong>{{ ($overallStats->total ?? 0) ? round((($overallStats->present ?? 0) / $overallStats->total) * 100) : 0 }}%</strong></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">{{ __('pages.daily_stats') }}</h3>
                            @forelse($dailyStats as $stat)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="fw-semibold">{{ $stat->date }}</div>
                                    <div class="small text-muted-theme">{{ __('pages.attendance_label') }} {{ $stat->present }} ({{ $stat->total ? round(($stat->present / $stat->total) * 100) : 0 }}%)</div>
                                </div>
                            @empty
                                <p class="small text-muted-theme mb-0">—</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">{{ __('pages.highest_attendance') }}</h3>
                            @forelse($userStats as $stat)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="fw-semibold">{{ $stat->first_name . ' ' . $stat->second_name }}</div>
                                    <div class="small text-muted-theme">{{ __('pages.attendance_rate') }}: {{ $stat->total ? round(($stat->present / $stat->total) * 100) : 0 }}%</div>
                                </div>
                            @empty
                                <p class="small text-muted-theme mb-0">—</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('attendance.partials.status-scripts')
@endsection
