@extends('layouts.app')

@section('content')
<div class="container animate-in mx-auto px-4 py-8">
    <div class="app-card card shadow-sm">
        <div class="card-body">
        <h1 class="page-title mb-4">{{ __('pages.attendance_on_date', ['date' => $date]) }}</h1>

        @if($attendanceRecords->count() === 0)
            <p class="text-right">{{ __('pages.no_attendance_records') }}.</p>
        @else
            <div class="w-full overflow-x-auto">
                <table class="table table-hover w-100 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ __('pages.server_name') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ __('pages.date') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ __('pages.status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ __('pages.permission_reason') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ __('pages.recorded_by') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">{{ __('pages.recorded_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendanceRecords as $record)
                            <tr>
                            <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    <a href="{{ route('attendance.user', $record->user_id) }}" class="text-blue-600 hover:underline">
                                        {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->session_date }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    <select 
                                        class="status-select bg-white border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        data-attendance-id="{{ $record->attendance_id }}"
                                        data-current-status="{{ $record->status }}"
                                        onchange="updateStatus(this)">
                                        <option value="Present" {{ $record->status === 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
                                        <option value="Absent" {{ $record->status === 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
                                        <option value="Late" {{ $record->status === 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
                                        <option value="Permission" {{ $record->status === 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    <div id="permission-reason-{{ $record->attendance_id }}" class="{{ $record->status === 'Permission' ? '' : 'hidden' }}">
                                        <input 
                                            type="text" 
                                            class="permission-reason bg-white border border-gray-300 rounded-md px-3 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="{{ __('pages.permission_reason') }}"
                                            value="{{ $record->permission_reason }}"
                                            onchange="updatePermissionReason(this, {{ $record->attendance_id }})">
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->attendance_time }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Daily Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">{{ __('pages.today_stats') }}</h3>
                    @php
                        $total = $attendanceRecords->count();
                        $present = $attendanceRecords->where('status', 'Present')->count();
                        $absent = $attendanceRecords->where('status', 'Absent')->count();
                        $late = $attendanceRecords->where('status', 'Late')->count();
                        $presentPercentage = round(($present / $total) * 100);
                    @endphp
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-600 mb-2">{{ __('pages.attendance_rate') }}</p>
                            <div class="w-full bg-gray-200 rounded-full h-4">
                                <div class="bg-green-600 h-4 rounded-full" style="width: {{ $presentPercentage }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $presentPercentage }}%</p>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">{{ $present }}</p>
                                <p class="text-sm text-gray-600">{{ __('pages.present') }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">{{ $absent }}</p>
                                <p class="text-sm text-gray-600">{{ __('pages.absent') }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-yellow-600">{{ $late }}</p>
                                <p class="text-sm text-gray-600">{{ __('pages.late') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- {{ __('pages.session_stats') }} -->
                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('pages.session_stats') }}</h3>
                    <div class="space-y-4">
                        @foreach($sessionStats as $stat)
                        <div class="border-b border-gray-200 pb-4 last:border-0 last:pb-0">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="mr-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $stat->session_title }}</p>
                                        <p class="text-sm text-gray-500">{{ __('pages.present_of_total', ['present' => $stat->present_count, 'total' => $stat->total_records]) }}</p>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ number_format(($stat->present_count / $stat->total_records) * 100, 1) }}%
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="relative pt-1">
                                    <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200">
                                        <div style="width: {{ ($stat->present_count / $stat->total_records) * 100 }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
    }`;
    alertDiv.innerHTML = `
        <div class="flex items-center">
            <div class="flex-shrink-0">
                ${type === 'success' 
                    ? '<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
                    : '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>'
                }
            </div>
            <div class="mr-3">
                <p class="text-sm font-medium">${message}</p>
            </div>
        </div>
    `;
    document.body.appendChild(alertDiv);
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

function updateStatus(select) {
    const attendanceId = select.dataset.attendanceId;
    const status = select.value;
    const permissionReasonDiv = document.getElementById(`permission-reason-${attendanceId}`);
    const permissionReasonInput = permissionReasonDiv.querySelector('input');

    if (status === 'Permission') {
        permissionReasonDiv.classList.remove('hidden');
        if (!permissionReasonInput.value) {
            showAlert(@json(__('pages.enter_permission_reason')), 'error');
            select.value = select.getAttribute('data-current-status') || 'Present';
            return;
        }
    } else {
        permissionReasonDiv.classList.add('hidden');
    }

    fetch(`/attendance/${attendanceId}/status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            status: status,
            permission_reason: permissionReasonInput.value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message);
            select.setAttribute('data-current-status', status);
        } else {
            showAlert(@json(__('pages.status_update_error')), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert(@json(__('pages.status_update_error')), 'error');
    });
}

function updatePermissionReason(input, attendanceId) {
    const select = input.closest('tr').querySelector('select');
    const status = select.value;

    if (status === 'Permission' && !input.value) {
        showAlert(@json(__('pages.enter_permission_reason')), 'error');
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
            showAlert(@json(__('pages.permission_update_error')), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert(@json(__('pages.permission_update_error')), 'error');
    });
}
</script>
@endpush
@endsection 


