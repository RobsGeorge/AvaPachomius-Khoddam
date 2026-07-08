@include('attendance.partials.status-messages-json')

@push('scripts')
<script>
const attendanceStatusMessages = JSON.parse(
    document.getElementById('attendance-status-messages').textContent
);

function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} app-toast-alert alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 4000);
}

function updateStatus(select) {
    const attendanceId = select.dataset.attendanceId;
    const status = select.value;
    const permissionReasonDivs = document.querySelectorAll(`[id="permission-reason-${attendanceId}"]`);
    const permissionReasonInput = select.closest('tr, article, td, dd')?.querySelector('.permission-reason')
        ?? permissionReasonDivs[0]?.querySelector('input');

    if (status === 'Permission') {
        permissionReasonDivs.forEach((div) => div.classList.remove('d-none'));
        if (permissionReasonInput && !permissionReasonInput.value) {
            showAlert(attendanceStatusMessages.enterPermissionReason, 'error');
            select.value = select.getAttribute('data-current-status') || 'Present';
            return;
        }
    } else {
        permissionReasonDivs.forEach((div) => div.classList.add('d-none'));
    }

    fetch(`/attendance/${attendanceId}/status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            status: status,
            permission_reason: permissionReasonInput ? permissionReasonInput.value : ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message);
            select.setAttribute('data-current-status', status);
        } else {
            showAlert(attendanceStatusMessages.statusUpdateError, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert(attendanceStatusMessages.statusUpdateError, 'error');
    });
}

function updatePermissionReason(input) {
    const attendanceId = input.dataset.attendanceId;
    const select = input.closest('tr, article, td, dd')?.querySelector('select');
    const status = select ? select.value : 'Permission';

    if (status === 'Permission' && !input.value) {
        showAlert(attendanceStatusMessages.enterPermissionReason, 'error');
        return;
    }

    fetch(`/attendance/${attendanceId}/status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            status: status,
            permission_reason: input.value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message);
        } else {
            showAlert(attendanceStatusMessages.permissionUpdateError, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert(attendanceStatusMessages.permissionUpdateError, 'error');
    });
}

function setRosterStatus(select) {
    const sessionId = select.dataset.sessionId;
    const userId = select.dataset.userId;
    const status = select.value;

    if (!status) {
        return;
    }

    let permissionReason = '';
    if (status === 'Permission') {
        permissionReason = window.prompt(attendanceStatusMessages.enterPermissionReason);
        if (!permissionReason) {
            select.selectedIndex = 0;
            return;
        }
    }

    fetch(`/sessions/${sessionId}/attendance`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            user_id: userId,
            status: status,
            permission_reason: permissionReason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message);
            window.location.reload();
        } else {
            showAlert(attendanceStatusMessages.statusUpdateError, 'error');
            select.selectedIndex = 0;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert(attendanceStatusMessages.statusUpdateError, 'error');
        select.selectedIndex = 0;
    });
}
</script>
@endpush
