@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
    <h1 class="text-2xl font-bold mb-6 text-right">سجل الحضور الكامل</h1>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <!-- Search and Filter Section -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search by Name -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">البحث بالاسم</label>
                    <input type="text" id="search" 
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        placeholder="ادخل اسم المستخدم">
                </div>

                <!-- Date Range -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">من تاريخ</label>
                    <input type="date" id="date_from"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">إلى تاريخ</label>
                    <input type="date" id="date_to"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select id="status"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">الكل</option>
                        <option value="Present">حاضر</option>
                        <option value="Absent">غائب</option>
                        <option value="Late">متأخر</option>
                        <option value="Permission">إذن</option>
                    </select>
                </div>

                <!-- Group By -->
                <div>
                    <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">تجميع حسب</label>
                    <select id="group_by"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">بدون تجميع</option>
                        <option value="date">التاريخ</option>
                        <option value="user">المستخدم</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">ترتيب حسب</label>
                    <select id="sort_by"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="date_desc">التاريخ (تنازلي)</option>
                        <option value="date_asc">التاريخ (تصاعدي)</option>
                        <option value="name_asc">الاسم (أ-ي)</option>
                        <option value="name_desc">الاسم (ي-أ)</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-4 space-x-reverse">
                <button type="button" id="resetFilters" class="btn btn-secondary">إعادة تعيين</button>
            </div>
        </div>
    </div>

    @if($attendanceRecords->isEmpty())
        <p class="text-right">لا توجد سجلات حضور.</p>
    @else
        <div id="attendance-content">
            @if(request('group_by') == 'date')
                @foreach($attendanceRecords->groupBy(function($record) {
                    return $record->session->session_date;
                }) as $date => $records)
                    <div class="mb-8">
                        <h2 class="text-xl font-bold mb-4 text-right">{{ $date }}</h2>
                        @include('attendance.partials.attendance-table', ['records' => $records])
                    </div>
                @endforeach
            @elseif(request('group_by') == 'user')
                @foreach($attendanceRecords->groupBy('user_id') as $userId => $records)
                    <div class="mb-8">
                        <h2 class="text-xl font-bold mb-4 text-right">
                            {{ $records->first()->user->first_name . ' ' . $records->first()->user->second_name . ' ' . $records->first()->user->third_name }}
                        </h2>
                        @include('attendance.partials.attendance-table', ['records' => $records])
                    </div>
                @endforeach
            @else
                @include('attendance.partials.attendance-table', ['records' => $attendanceRecords])
            @endif
        </div>

        <div class="mt-4">
            {{ $attendanceRecords->appends(request()->query())->links() }}
        </div>
    @endif
</div>

@push('styles')
<style>
    /* Pagination Styling */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination > * {
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        background-color: #f3f4f6;
        color: #374151;
        transition: all 0.2s;
    }

    .pagination > *:hover {
        background-color: #e5e7eb;
    }

    .pagination .active {
        background-color: #4f46e5;
        color: white;
    }

    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Add icons to pagination */
    .pagination .prev:before {
        content: "←";
        margin-right: 0.5rem;
    }

    .pagination .next:after {
        content: "→";
        margin-left: 0.5rem;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const dateFromInput = document.getElementById('date_from');
    const dateToInput = document.getElementById('date_to');
    const statusSelect = document.getElementById('status');
    const resetButton = document.getElementById('resetFilters');
    const rows = document.querySelectorAll('.attendance-row');

    function filterRows() {
        const searchTerm = searchInput.value.toLowerCase();
        const dateFrom = dateFromInput.value;
        const dateTo = dateToInput.value;
        const status = statusSelect.value;

        rows.forEach(row => {
            const name = row.dataset.name;
            const date = row.dataset.date;
            const rowStatus = row.dataset.status;

            const matchesSearch = !searchTerm || name.includes(searchTerm);
            const matchesDateFrom = !dateFrom || date >= dateFrom;
            const matchesDateTo = !dateTo || date <= dateTo;
            const matchesStatus = !status || rowStatus === status;

            row.style.display = matchesSearch && matchesDateFrom && matchesDateTo && matchesStatus ? '' : 'none';
        });
    }

    // Add event listeners for instant filtering
    searchInput.addEventListener('input', filterRows);
    dateFromInput.addEventListener('change', filterRows);
    dateToInput.addEventListener('change', filterRows);
    statusSelect.addEventListener('change', filterRows);

    // Reset filters
    resetButton.addEventListener('click', function() {
        searchInput.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        statusSelect.value = '';
        filterRows();
    });
});
</script>
@endpush
@endsection 