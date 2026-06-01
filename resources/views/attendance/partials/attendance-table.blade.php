<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>{{ __('pages.user') }}</th>
                <th>{{ __('pages.lecture') }}</th>
                <th>{{ __('pages.date') }}</th>
                <th>{{ __('pages.status') }}</th>
                <th>{{ __('pages.permission_reason') }}</th>
                <th>{{ __('pages.recorded_by') }}</th>
                <th>{{ __('pages.recorded_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr class="attendance-row"
                    data-name="{{ strtolower($record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name) }}"
                    data-date="{{ $record->session->session_date }}"
                    data-status="{{ $record->status }}"
                    data-attendance-id="{{ $record->attendance_id }}">
                    <td>
                        <a href="{{ route('attendance.user', $record->user_id) }}">
                            {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                        </a>
                    </td>
                    <td>{{ $record->session->session_title }}</td>
                    <td>{{ $record->session->session_date }}</td>
                    <td>
                        <select class="form-select form-select-sm status-select"
                                data-attendance-id="{{ $record->attendance_id }}"
                                onchange="updateAttendanceStatus(this)">
                            <option value="Present" {{ $record->status == 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
                            <option value="Absent" {{ $record->status == 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
                            <option value="Late" {{ $record->status == 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
                            <option value="Permission" {{ $record->status == 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
                        </select>
                    </td>
                    <td>
                        <input type="text"
                               class="form-control form-control-sm permission-reason"
                               data-attendance-id="{{ $record->attendance_id }}"
                               value="{{ $record->permission_reason }}"
                               placeholder="{{ __('pages.permission_reason_placeholder') }}"
                               style="display: {{ $record->status == 'Permission' ? 'block' : 'none' }}"
                               onchange="updatePermissionReason(this)">
                    </td>
                    <td>{{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}</td>
                    <td>{{ $record->attendance_time }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@push('scripts')
<script>
const attendanceMessages = {
    statusUpdated: @json(__('pages.status_updated')),
    statusError: @json(__('pages.status_update_error')),
    permissionUpdated: @json(__('pages.permission_updated')),
    permissionError: @json(__('pages.permission_update_error')),
    enterReason: @json(__('pages.enter_permission_reason')),
};

function updateAttendanceStatus(selectElement) {
    const attendanceId = selectElement.dataset.attendanceId;
    const status = selectElement.value;
    const row = selectElement.closest('tr');
    const permissionReasonInput = row.querySelector('.permission-reason');
    permissionReasonInput.style.display = status === 'Permission' ? 'block' : 'none';
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    fetch(`/attendance/update-status/${attendanceId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({ status })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(attendanceMessages.statusUpdated);
        } else {
            alert(attendanceMessages.statusError);
        }
    })
    .catch(() => alert(attendanceMessages.statusError));
}

function updatePermissionReason(inputElement) {
    const attendanceId = inputElement.dataset.attendanceId;
    const reason = inputElement.value;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (!reason.trim()) {
        alert(attendanceMessages.enterReason);
        return;
    }

    fetch(`/attendance/update-permission/${attendanceId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({ permission_reason: reason })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(attendanceMessages.permissionUpdated);
        } else {
            alert(attendanceMessages.permissionError);
        }
    })
    .catch(() => alert(attendanceMessages.permissionError));
}
</script>
@endpush
