@if($attendance)
    <select class="status-select form-select form-select-sm"
            data-attendance-id="{{ $attendance->attendance_id }}"
            data-current-status="{{ $attendance->status }}"
            onchange="updateStatus(this)">
        <option value="Present" {{ $attendance->status === 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
        <option value="Absent" {{ $attendance->status === 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
        <option value="Late" {{ $attendance->status === 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
        <option value="Permission" {{ $attendance->status === 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
    </select>
    <div id="permission-reason-{{ $attendance->attendance_id }}" class="mt-1 {{ $attendance->status === 'Permission' ? '' : 'd-none' }}">
        <input type="text"
               class="permission-reason form-control form-control-sm"
               data-attendance-id="{{ $attendance->attendance_id }}"
               placeholder="{{ __('pages.permission_reason') }}"
               value="{{ $attendance->permission_reason }}"
               onchange="updatePermissionReason(this)">
    </div>
@else
    <select class="form-select form-select-sm roster-status-select"
            data-session-id="{{ $session->session_id }}"
            data-user-id="{{ $student->user_id }}"
            onchange="setRosterStatus(this)">
        <option value="" selected disabled>{{ __('pages.not_recorded') }}</option>
        <option value="Present">{{ __('pages.present') }}</option>
        <option value="Absent">{{ __('pages.absent') }}</option>
        <option value="Late">{{ __('pages.late') }}</option>
        <option value="Permission">{{ __('pages.permission') }}</option>
    </select>
@endif
