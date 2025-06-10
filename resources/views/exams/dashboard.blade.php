@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow rounded-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-900">إدارة الامتحانات</h2>
            <button onclick="showAddExamModal()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                إضافة امتحان جديد
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">اسم الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">مدة الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">مواعيد الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">مصادر المذاكرة</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($exams as $exam)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $exam->exam_name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $exam->duration_minutes }} دقيقة</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                @foreach($exam->schedules as $schedule)
                                    <div class="mb-2">
                                        {{ $schedule->scheduled_date->format('Y-m-d H:i') }}
                                        @if($schedule->is_completed)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">تم</span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">لم يتم</span>
                                        @endif
                                    </div>
                                @endforeach
                                <button onclick="showAddScheduleModal({{ $exam->exam_id }})" class="text-blue-500 hover:text-blue-700">
                                    + إضافة موعد
                                </button>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $exam->study_resources }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="flex space-x-2">
                                    <button onclick="showEditExamModal({{ $exam->exam_id }})" class="text-blue-500 hover:text-blue-700">
                                        تعديل
                                    </button>
                                    <button onclick="showResultsModal({{ $exam->exam_id }})" class="text-green-500 hover:text-green-700">
                                        النتائج
                                    </button>
                                    <button onclick="deleteExam({{ $exam->exam_id }})" class="text-red-500 hover:text-red-700">
                                        حذف
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Exam Modal -->
<div id="addExamModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">إضافة امتحان جديد</h3>
            <form id="addExamForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">اسم الامتحان</label>
                    <input type="text" name="exam_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">مدة الامتحان (بالدقائق)</label>
                    <input type="number" name="duration_minutes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">مصادر المذاكرة</label>
                    <textarea name="study_resources" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideAddExamModal()" class="bg-gray-500 text-white px-4 py-2 rounded-md">إلغاء</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Schedule Modal -->
<div id="addScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">إضافة موعد امتحان</h3>
            <form id="addScheduleForm" class="space-y-4">
                <input type="hidden" name="exam_id" id="scheduleExamId">
                <div>
                    <label class="block text-sm font-medium text-gray-700">موعد الامتحان</label>
                    <input type="datetime-local" name="scheduled_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideAddScheduleModal()" class="bg-gray-500 text-white px-4 py-2 rounded-md">إلغاء</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Results Modal -->
<div id="resultsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full">
    <div class="relative top-20 mx-auto p-5 border w-3/4 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">نتائج الامتحان</h3>
            <div class="overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الطالب</th>
                            <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الدرجة</th>
                            <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="resultsTableBody" class="divide-y divide-gray-200">
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end mt-4">
                <button onclick="hideResultsModal()" class="bg-gray-500 text-white px-4 py-2 rounded-md">إغلاق</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function showAddExamModal() {
    document.getElementById('addExamModal').classList.remove('hidden');
}

function hideAddExamModal() {
    document.getElementById('addExamModal').classList.add('hidden');
}

function showAddScheduleModal(examId) {
    document.getElementById('scheduleExamId').value = examId;
    document.getElementById('addScheduleModal').classList.remove('hidden');
}

function hideAddScheduleModal() {
    document.getElementById('addScheduleModal').classList.add('hidden');
}

function showResultsModal(examId) {
    // Here you would typically fetch the results for the exam
    document.getElementById('resultsModal').classList.remove('hidden');
}

function hideResultsModal() {
    document.getElementById('resultsModal').classList.add('hidden');
}

function deleteExam(examId) {
    if (confirm('هل أنت متأكد من حذف هذا الامتحان؟')) {
        // Here you would typically send a delete request to the server
        alert('تم حذف الامتحان بنجاح');
    }
}

// Form submissions
document.getElementById('addExamForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Here you would typically send the form data to the server
    hideAddExamModal();
    alert('تم إضافة الامتحان بنجاح');
});

document.getElementById('addScheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Here you would typically send the form data to the server
    hideAddScheduleModal();
    alert('تم إضافة موعد الامتحان بنجاح');
});
</script>
@endpush
@endsection 