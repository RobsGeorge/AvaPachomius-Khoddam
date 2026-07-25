@php
    $session = $sessionReportSession ?? null;
@endphp

@if($session)
    @php
        $sessionDate = $session->session_date?->format('Y-m-d');
        $canCloseSession = ($canManageSessionAttendance ?? false)
            && ! $session->isAttendanceClosed()
            && $sessionDate
            && $sessionDate <= ($todayLocal ?? now()->toDateString());
    @endphp
    <div class="px-3 py-3 border-bottom bg-light">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    @if($session->isAttendanceClosed())
                        <span class="badge bg-success">{{ __('pages.attendance_status_closed') }}</span>
                    @else
                        <span class="badge bg-warning text-dark">{{ __('pages.attendance_status_open') }}</span>
                    @endif
                    @if(($canManageSessionAttendance ?? false) && ($sessionReportMissingCount ?? 0) > 0)
                        <span class="badge bg-danger">
                            {{ __('pages.missing_records_count', ['count' => $sessionReportMissingCount]) }}
                        </span>
                    @endif
                </div>
                @if($session->isAttendanceClosed() && $session->attendance_closed_at)
                    <p class="small text-muted-theme mb-0">
                        {{ __('pages.attendance_closed_on', ['date' => $session->attendance_closed_at->format('Y-m-d H:i')]) }}
                        @if($session->attendanceClosedBy)
                            · {{ __('pages.attendance_closed_by', ['name' => $session->attendanceClosedBy->displayName()]) }}
                        @endif
                    </p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('sessions.index', ['session_id' => $session->session_id]) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-calendar-week"></i> {{ __('pages.view_sessions_list') }}
                </a>
                @if($canCloseSession)
                    <form method="POST"
                          action="{{ route('sessions.close-attendance', $session->session_id) }}"
                          data-confirm="{{ __('pages.confirm_close_attendance') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-lock"></i> {{ __('pages.close_attendance') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endif
