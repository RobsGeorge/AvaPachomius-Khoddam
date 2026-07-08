@extends('layouts.app')

@section('title', __('pages.my_attendance_title'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="app-card card">
        <div class="card-body">
            <h1 class="page-title mb-4">{{ __('pages.my_attendance_title') }}</h1>

            @if($attendanceRecords->count() === 0)
                <p class="text-muted-theme text-center py-4">{{ __('pages.no_attendance_records') }}</p>
            @else
                @foreach($attendanceRecords as $record)
                    <article class="data-card">
                        <div class="data-card-title">{{ $record->session->session_title ?? __('pages.unspecified') }}</div>
                        <dl class="data-meta-list mb-0">
                            <div class="data-meta-row">
                                <dt>{{ __('pages.date') }}</dt>
                                <dd>{{ $record->display_session_date ?? __('pages.unspecified') }}</dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.status') }}</dt>
                                <dd>
                                    @if($record->status === 'Present')
                                        <span class="badge bg-success">{{ __('pages.present') }}</span>
                                    @elseif($record->status === 'Absent')
                                        <span class="badge bg-danger">{{ __('pages.absent') }}</span>
                                    @elseif($record->status === 'Permission')
                                        <span class="badge bg-info">{{ __('pages.permission') }}</span>
                                        @if($record->permission_reason)
                                            <div class="small text-muted-theme mt-1">{{ $record->permission_reason }}</div>
                                        @endif
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('pages.late') }}</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.recorded_at') }}</dt>
                                <dd>{{ $record->display_attendance_time ?? '—' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach

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
