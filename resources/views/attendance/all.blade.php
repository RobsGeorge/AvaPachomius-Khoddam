@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">سجل الحضور</h1>
            <div class="flex space-x-4 rtl:space-x-reverse">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="بحث..." 
                           class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none rtl:left-auto rtl:right-0 rtl:pl-0 rtl:pr-3">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="flex space-x-2 rtl:space-x-reverse">
                    <input type="date" id="dateFilter" 
                           class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <select id="statusFilter" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">كل الحالات</option>
                        <option value="present">حاضر</option>
                        <option value="absent">غائب</option>
                        <option value="late">متأخر</option>
                        <option value="permission">إذن</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الاسم</th>
                        <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                        <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                        <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">سبب الإذن</th>
                        <th class="px-6 py-3 border-b border-gray-200 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendanceRecords as $record)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->user->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $record->date }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <select class="state-select px-2 py-1 border rounded" 
                                    data-id="{{ $record->id }}"
                                    data-current-state="{{ $record->state }}">
                                <option value="present" {{ $record->state === 'present' ? 'selected' : '' }}>حاضر</option>
                                <option value="absent" {{ $record->state === 'absent' ? 'selected' : '' }}>غائب</option>
                                <option value="late" {{ $record->state === 'late' ? 'selected' : '' }}>متأخر</option>
                                <option value="permission" {{ $record->state === 'permission' ? 'selected' : '' }}>إذن</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <input type="text" 
                                   class="permission-reason px-2 py-1 border rounded w-full" 
                                   value="{{ $record->permission_reason }}"
                                   data-id="{{ $record->id }}"
                                   placeholder="سبب الإذن"
                                   {{ $record->state !== 'permission' ? 'disabled' : '' }}>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <a href="{{ route('attendance.user', $record->user_id) }}" 
                               class="text-blue-600 hover:text-blue-900">عرض السجل</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $attendanceRecords->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const dateFilter = document.getElementById('dateFilter');
    const statusFilter = document.getElementById('statusFilter');
    const table = document.querySelector('table');
    const rows = table.getElementsByTagName('tr');

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const dateValue = dateFilter.value;
        const statusValue = statusFilter.value;

        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            const name = cells[0].textContent.toLowerCase();
            const date = cells[1].textContent;
            const status = cells[2].querySelector('select').value;

            const matchesSearch = name.includes(searchText);
            const matchesDate = !dateValue || date === dateValue;
            const matchesStatus = !statusValue || status === statusValue;

            row.style.display = matchesSearch && matchesDate && matchesStatus ? '' : 'none';
        }
    }

    searchInput.addEventListener('input', filterTable);
    dateFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Handle state changes
    document.querySelectorAll('.state-select').forEach(select => {
        select.addEventListener('change', function() {
            const recordId = this.dataset.id;
            const newState = this.value;
            const permissionInput = this.closest('tr').querySelector('.permission-reason');
            
            // Enable/disable permission reason input
            permissionInput.disabled = newState !== 'permission';
            if (newState !== 'permission') {
                permissionInput.value = '';
            }

            // Update the state
            fetch(`/attendance/${recordId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ state: newState })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('تم تحديث الحالة بنجاح');
                } else {
                    // Show error message
                    alert('حدث خطأ أثناء تحديث الحالة');
                    // Revert the select to its previous value
                    this.value = this.dataset.currentState;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء تحديث الحالة');
                // Revert the select to its previous value
                this.value = this.dataset.currentState;
            });
        });
    });

    // Handle permission reason changes
    document.querySelectorAll('.permission-reason').forEach(input => {
        input.addEventListener('change', function() {
            const recordId = this.dataset.id;
            const reason = this.value;

            fetch(`/attendance/${recordId}/permission-reason`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ permission_reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('تم تحديث سبب الإذن بنجاح');
                } else {
                    // Show error message
                    alert('حدث خطأ أثناء تحديث سبب الإذن');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء تحديث سبب الإذن');
            });
        });
    });
});
</script>
@endpush
@endsection 