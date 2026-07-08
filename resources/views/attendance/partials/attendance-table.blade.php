<div class="table-responsive d-none d-lg-block admin-table-desktop">
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
                        <div class="permission-reason-field {{ $record->status == 'Permission' ? '' : 'd-none' }}">
                            <input type="text"
                                   class="form-control form-control-sm permission-reason"
                                   data-attendance-id="{{ $record->attendance_id }}"
                                   value="{{ $record->permission_reason }}"
                                   placeholder="{{ __('pages.permission_reason_placeholder') }}"
                                   onchange="updatePermissionReason(this)">
                        </div>
                    </td>
                    <td>{{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}</td>
                    <td>{{ $record->attendance_time }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="d-lg-none admin-data-cards student-data-hub">
    @foreach($records as $record)
        <article class="data-card attendance-row"
                 data-name="{{ strtolower($record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name) }}"
                 data-date="{{ $record->session->session_date }}"
                 data-status="{{ $record->status }}"
                 data-attendance-id="{{ $record->attendance_id }}">
            <div class="data-card-title">
                <a href="{{ route('attendance.user', $record->user_id) }}" class="text-decoration-none">
                    {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                </a>
            </div>
            <dl class="data-meta-list mb-0">
                <div class="data-meta-row">
                    <dt>{{ __('pages.lecture') }}</dt>
                    <dd>{{ $record->session->session_title }}</dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.date') }}</dt>
                    <dd>{{ $record->session->session_date }}</dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.status') }}</dt>
                    <dd>
                        <select class="form-select form-select-sm status-select"
                                data-attendance-id="{{ $record->attendance_id }}"
                                onchange="updateAttendanceStatus(this)">
                            <option value="Present" {{ $record->status == 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
                            <option value="Absent" {{ $record->status == 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
                            <option value="Late" {{ $record->status == 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
                            <option value="Permission" {{ $record->status == 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
                        </select>
                    </dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.permission_reason') }}</dt>
                    <dd>
                        <div class="permission-reason-field {{ $record->status == 'Permission' ? '' : 'd-none' }}">
                            <input type="text"
                                   class="form-control form-control-sm permission-reason"
                                   data-attendance-id="{{ $record->attendance_id }}"
                                   value="{{ $record->permission_reason }}"
                                   placeholder="{{ __('pages.permission_reason_placeholder') }}"
                                   onchange="updatePermissionReason(this)">
                        </div>
                    </dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.recorded_by') }}</dt>
                    <dd>{{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}</dd>
                </div>
                <div class="data-meta-row">
                    <dt>{{ __('pages.recorded_at') }}</dt>
                    <dd>{{ $record->attendance_time }}</dd>
                </div>
            </dl>
        </article>
    @endforeach
</div>

@include('attendance.partials.status-messages-json')

@push('scripts')
<script>
const attendanceTableMessages = JSON.parse(
    document.getElementById('attendance-status-messages').textContent
);

function updateAttendanceStatus(selectElement) {
    const attendanceId = selectElement.dataset.attendanceId;
    const status = selectElement.value;
    const row = selectElement.closest('.attendance-row');
    const permissionReasonField = row.querySelector('.permission-reason-field');
    if (permissionReasonField) {
        permissionReasonField.classList.toggle('d-none', status !== 'Permission');
    }
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
            alert(attendanceTableMessages.statusUpdated);
        } else {
            alert(attendanceTableMessages.statusUpdateError);
        }
    })
    .catch(() => alert(attendanceTableMessages.statusUpdateError));
}

function updatePermissionReason(inputElement) {
    const attendanceId = inputElement.dataset.attendanceId;
    const reason = inputElement.value;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    if (!reason.trim()) {
        alert(attendanceTableMessages.enterPermissionReason);
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
            alert(attendanceTableMessages.permissionUpdated);
        } else {
            alert(attendanceTableMessages.permissionUpdateError);
        }
    })
    .catch(() => alert(attendanceTableMessages.permissionUpdateError));
}
</script>
@endpush
