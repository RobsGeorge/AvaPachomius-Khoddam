@extends('layouts.app')

@section('title', __('pages.my_attendance_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card">
        <div class="card-body">
            <h1 class="page-title mb-4">{{ __('pages.my_attendance_title') }}</h1>

            @if($attendanceRecords->count() === 0)
                <p class="text-muted-theme text-center py-4">{{ __('pages.no_attendance_records') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.lecture') }}</th>
                                <th>{{ __('pages.date') }}</th>
                                <th>{{ __('pages.status') }}</th>
                                <th>{{ __('pages.permission_reason') }}</th>
                                <th>{{ __('pages.recorded_at') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attendanceRecords as $record)
                                <tr>
                                    <td>{{ $record->session->session_title ?? __('pages.unspecified') }}</td>
                                    <td>{{ $record->session_date ?? __('pages.unspecified') }}</td>
                                    <td>
                                        @if($record->status === 'Present')
                                            <span class="badge bg-success">{{ __('pages.present') }}</span>
                                        @elseif($record->status === 'Absent')
                                            <span class="badge bg-danger">{{ __('pages.absent') }}</span>
                                        @elseif($record->status === 'Permission')
                                            <span class="badge bg-info">{{ __('pages.permission') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('pages.late') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $record->permission_reason }}</td>
                                    <td>{{ $record->attendance_time ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-4 mt-4">
                    <div class="col-md-6">
                        <div class="app-tile h-100">
                            <h3 class="h5 page-title">{{ __('pages.attendance_stats') }}</h3>
                            @php
                                $total = $attendanceRecords->count();
                                $present = $attendanceRecords->where('status', 'Present')->count();
                                $absent = $attendanceRecords->where('status', 'Absent')->count();
                                $late = $attendanceRecords->where('status', 'Late')->count();
                                $presentPercentage = $total > 0 ? round(($present / $total) * 100) : 0;
                            @endphp
                            <p class="text-muted-theme mb-2">{{ __('pages.attendance_rate') }}</p>
                            <div class="progress mb-2" style="height:1rem;">
                                <div class="progress-bar bg-success" style="width: {{ $presentPercentage }}%"></div>
                            </div>
                            <p class="small text-muted-theme">{{ $presentPercentage }}%</p>
                            <div class="row text-center g-2">
                                <div class="col-4"><strong class="text-success">{{ $present }}</strong><br><small>{{ __('pages.present') }}</small></div>
                                <div class="col-4"><strong class="text-danger">{{ $absent }}</strong><br><small>{{ __('pages.absent') }}</small></div>
                                <div class="col-4"><strong class="text-warning">{{ $late }}</strong><br><small>{{ __('pages.late') }}</small></div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
