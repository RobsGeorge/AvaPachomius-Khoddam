@extends('layouts.app')

@section('content')
<div class="overflow-x-auto">
    <table class="min-w-full bg-white border border-gray-300">
        <thead>
            <tr>
                <th class="px-6 py-3 border-b text-right">المستخدم</th>
                <th class="px-6 py-3 border-b text-right">المحاضرة</th>
                <th class="px-6 py-3 border-b text-right">التاريخ</th>
                <th class="px-6 py-3 border-b text-right">الحالة</th>
                <th class="px-6 py-3 border-b text-right">سبب الإذن</th>
                <th class="px-6 py-3 border-b text-right">تم التسجيل بواسطة</th>
                <th class="px-6 py-3 border-b text-right">وقت التسجيل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $record)
                <tr class="attendance-row" 
                    data-name="{{ strtolower($record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name) }}"
                    data-date="{{ $record->session->session_date }}"
                    data-status="{{ $record->status }}"
                    data-attendance-id="{{ $record->attendance_id }}">
                    <td class="px-6 py-4 border-b text-right">
                        <a href="{{ route('attendance.user', $record->user_id) }}" class="text-blue-600 hover:underline">
                            {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                        </a>
                    </td>
                    <td class="px-6 py-4 border-b text-right">{{ $record->session->session_title }}</td>
                    <td class="px-6 py-4 border-b text-right">{{ $record->session->session_date }}</td>
                    <td class="px-6 py-4 border-b text-right">
                        <select class="status-select rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                data-attendance-id="{{ $record->attendance_id }}"
                                onchange="updateAttendanceStatus(this)">
                            <option value="Present" {{ $record->status == 'Present' ? 'selected' : '' }}>حاضر</option>
                            <option value="Absent" {{ $record->status == 'Absent' ? 'selected' : '' }}>غائب</option>
                            <option value="Late" {{ $record->status == 'Late' ? 'selected' : '' }}>متأخر</option>
                            <option value="Permission" {{ $record->status == 'Permission' ? 'selected' : '' }}>إذن</option>
                        </select>
                    </td>
                    <td class="px-6 py-4 border-b text-right">
                        <input type="text" 
                               class="permission-reason rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               data-attendance-id="{{ $record->attendance_id }}"
                               value="{{ $record->permission_reason }}"
                               placeholder="سبب الإذن"
                               style="display: {{ $record->status == 'Permission' ? 'block' : 'none' }}"
                               onchange="updatePermissionReason(this)">
                    </td>
                    <td class="px-6 py-4 border-b text-right">
                        {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                    </td>
                    <td class="px-6 py-4 border-b text-right">{{ $record->attendance_time }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@push('scripts')
<script>
function updateAttendanceStatus(selectElement) {
    const attendanceId = selectElement.dataset.attendanceId;
    const status = selectElement.value;
    const row = selectElement.closest('tr');
    const permissionReasonInput = row.querySelector('.permission-reason');

    // Show/hide permission reason input
    permissionReasonInput.style.display = status === 'Permission' ? 'block' : 'none';

    // Get CSRF token from meta tag
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // Send update request
    fetch(`/attendance/update-status/${attendanceId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
            status: status,
            _token: token
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            showNotification('تم تحديث الحالة بنجاح', 'success');
            // Update the row's data-status attribute
            row.dataset.status = status;
        } else {
            // Show error message
            showNotification('حدث خطأ أثناء تحديث الحالة', 'error');
            // Revert the select to its previous value
            selectElement.value = data.previousStatus;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('حدث خطأ أثناء تحديث الحالة', 'error');
        // Revert the select to its previous value
        selectElement.value = row.dataset.status;
    });
}

function updatePermissionReason(inputElement) {
    const attendanceId = inputElement.dataset.attendanceId;
    const reason = inputElement.value;
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    fetch(`/attendance/update-permission-reason/${attendanceId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({
            permission_reason: reason,
            _token: token
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showNotification('تم تحديث سبب الإذن بنجاح', 'success');
        } else {
            showNotification('حدث خطأ أثناء تحديث سبب الإذن', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('حدث خطأ أثناء تحديث سبب الإذن', 'error');
    });
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white z-50`;
    notification.textContent = message;
    document.body.appendChild(notification);

    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endpush 