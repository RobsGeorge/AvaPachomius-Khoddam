@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div class="mr-4">
                    <h3 class="text-lg font-semibold text-gray-900">إجمالي الامتحانات</h3>
                    <p class="text-2xl font-bold text-gray-900">{{ $totalExams }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="mr-4">
                    <h3 class="text-lg font-semibold text-gray-900">الامتحانات القادمة</h3>
                    <p class="text-2xl font-bold text-gray-900">{{ $upcomingExams }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="mr-4">
                    <h3 class="text-lg font-semibold text-gray-900">الامتحانات المكتملة</h3>
                    <p class="text-2xl font-bold text-gray-900">{{ $completedExams }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">إجراءات سريعة</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button onclick="showAddExamModal()" class="flex items-center justify-center p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                <svg class="h-6 w-6 text-blue-500 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>إضافة امتحان جديد</span>
            </button>
            <button onclick="showScheduleExamModal()" class="flex items-center justify-center p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                <svg class="h-6 w-6 text-green-500 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span>جدولة امتحان</span>
            </button>
            <button onclick="showResultsModal()" class="flex items-center justify-center p-4 border border-gray-300 rounded-lg hover:bg-gray-50">
                <svg class="h-6 w-6 text-yellow-500 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span>عرض النتائج</span>
            </button>
        </div>
    </div>

    <!-- Upcoming Exams -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900">الامتحانات القادمة</h2>
            <a href="{{ route('exams.dashboard') }}" class="text-blue-500 hover:text-blue-700">عرض الكل</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">اسم الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">التاريخ</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">المدة</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">عدد المسجلين</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($upcomingExamSchedules as $schedule)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->exam->exam_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->exam->duration_minutes }} دقيقة</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->results->count() }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="flex space-x-2">
                                    <button onclick="editSchedule({{ $schedule->schedule_id }})" class="text-blue-500 hover:text-blue-700">تعديل</button>
                                    <button onclick="viewRegistrations({{ $schedule->schedule_id }})" class="text-green-500 hover:text-green-700">المسجلين</button>
                                    <button onclick="deleteSchedule({{ $schedule->schedule_id }})" class="text-red-500 hover:text-red-700">حذف</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Results -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-900">آخر النتائج</h2>
            <a href="{{ route('exams.dashboard') }}" class="text-blue-500 hover:text-blue-700">عرض الكل</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الطالب</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">التاريخ</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الدرجة</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($recentResults as $result)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $result->user->name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $result->exam->exam_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $result->schedule->scheduled_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $result->score }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <button onclick="editResult({{ $result->result_id }})" class="text-blue-500 hover:text-blue-700">تعديل</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showAddExamModal() {
    // Implementation for showing add exam modal
}

function showScheduleExamModal() {
    // Implementation for showing schedule exam modal
}

function showResultsModal() {
    // Implementation for showing results modal
}

function editSchedule(scheduleId) {
    // Implementation for editing schedule
}

function viewRegistrations(scheduleId) {
    // Implementation for viewing registrations
}

function deleteSchedule(scheduleId) {
    if (confirm('هل أنت متأكد من حذف هذا الموعد؟')) {
        // Implementation for deleting schedule
    }
}

function editResult(resultId) {
    // Implementation for editing result
}
</script>
@endpush
@endsection 