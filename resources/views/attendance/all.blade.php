@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">سجل الحضور الكامل</h1>

        @if(session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif

        @if($attendanceRecords->count() === 0)
            <p class="text-right">لا توجد سجلات حضور.</p>
        @else
            <div class="w-full overflow-x-auto">
                <table class="w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">المستخدم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">المحاضرة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">التاريخ</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">الحالة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">سبب الإذن</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">تم التسجيل بواسطة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">وقت التسجيل</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($attendanceRecords as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    <a href="{{ route('attendance.user', $record->user_id) }}" class="text-blue-600 hover:underline">
                                        {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->session->session_title }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    <a href="{{ route('attendance.by-date', $record->session_date) }}" class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $record->session_date }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    <select 
                                        class="status-select bg-white border border-gray-300 rounded-md px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        data-attendance-id="{{ $record->attendance_id }}"
                                        onchange="updateStatus(this)">
                                        <option value="Present" {{ $record->status === 'Present' ? 'selected' : '' }}>حاضر</option>
                                        <option value="Absent" {{ $record->status === 'Absent' ? 'selected' : '' }}>غائب</option>
                                        <option value="Late" {{ $record->status === 'Late' ? 'selected' : '' }}>متأخر</option>
                                        <option value="Permission" {{ $record->status === 'Permission' ? 'selected' : '' }}>إذن</option>
                                    </select>
                                    <div id="permission-reason-{{ $record->attendance_id }}" class="mt-2 {{ $record->status === 'Permission' ? '' : 'hidden' }}">
                                        <input 
                                            type="text" 
                                            class="permission-reason bg-white border border-gray-300 rounded-md px-3 py-1 text-sm w-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="سبب الإذن"
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

            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Overall Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">إحصائيات عامة</h3>
                    <div class="space-y-2">
                        <p class="text-gray-600">إجمالي السجلات: <span class="font-semibold">{{ $overallStats->total }}</span></p>
                        <p class="text-gray-600">الحاضرين: <span class="font-semibold text-green-600">{{ $overallStats->present }}</span></p>
                        <p class="text-gray-600">الغائبين: <span class="font-semibold text-red-600">{{ $overallStats->absent }}</span></p>
                        <p class="text-gray-600">المتأخرين: <span class="font-semibold text-yellow-600">{{ $overallStats->late }}</span></p>
                        <p class="text-gray-600">نسبة الحضور: <span class="font-semibold">{{ round(($overallStats->present / $overallStats->total) * 100) }}%</span></p>
                    </div>
                </div>

                <!-- Daily Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">إحصائيات يومية</h3>
                    <div class="space-y-2">
                        @foreach($dailyStats as $stat)
                            <div class="border-b pb-2">
                                <p class="text-gray-800 font-medium">{{ $stat->date }}</p>
                                <p class="text-sm text-gray-600">الحضور: {{ $stat->present }} ({{ round(($stat->present / $stat->total) * 100) }}%)</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- User Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">أعلى نسبة حضور</h3>
                    <div class="space-y-2">
                        @foreach($userStats as $stat)
                            <div class="border-b pb-2">
                                <p class="text-gray-800 font-medium">{{ $stat->first_name . ' ' . $stat->second_name }}</p>
                                <p class="text-sm text-gray-600">نسبة الحضور: {{ round(($stat->present / $stat->total) * 100) }}%</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4">
                {{ $attendanceRecords->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function updateStatus(select) {
    const attendanceId = select.dataset.attendanceId;
    const status = select.value;
    const permissionReasonDiv = document.getElementById(`permission-reason-${attendanceId}`);
    const permissionReasonInput = permissionReasonDiv.querySelector('input');

    if (status === 'Permission') {
        permissionReasonDiv.classList.remove('hidden');
        if (!permissionReasonInput.value) {
            alert('الرجاء إدخال سبب الإذن');
            select.value = '{{ $record->status }}';
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
            alert(data.message);
        } else {
            alert('حدث خطأ أثناء تحديث الحالة');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تحديث الحالة');
    });
}

function updatePermissionReason(input, attendanceId) {
    const select = input.closest('td').querySelector('select');
    const status = select.value;

    if (status === 'Permission' && !input.value) {
        alert('الرجاء إدخال سبب الإذن');
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
            alert(data.message);
        } else {
            alert('حدث خطأ أثناء تحديث سبب الإذن');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تحديث سبب الإذن');
    });
}
</script>
@endpush
@endsection 