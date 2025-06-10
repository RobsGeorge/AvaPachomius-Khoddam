@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow rounded-lg p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">مواعيد ونتائج الامتحانات</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">اسم الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">مدة الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">مواعيد الامتحان</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">تم الامتحان؟</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">مصادر المذاكرة</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">درجتي</th>
                        <th class="px-6 py-3 text-right text-sm font-medium text-gray-900">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($exams as $exam)
                        @foreach($exam->schedules as $schedule)
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $exam->exam_name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $exam->duration_minutes }} دقيقة</td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $schedule->scheduled_date->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @if($schedule->is_completed)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">تم</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">لم يتم</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $exam->study_resources }}</td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    @php
                                        $result = $exam->results->where('schedule_id', $schedule->schedule_id)->first();
                                    @endphp
                                    {{ $result ? $result->score : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    @if(!$schedule->is_completed)
                                        <button onclick="selectSchedule({{ $schedule->schedule_id }})" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            اختيار المعاد المناسب للامتحان
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function selectSchedule(scheduleId) {
    // Here you can implement the logic to handle schedule selection
    // For example, show a modal or redirect to a confirmation page
    alert('تم اختيار المعاد بنجاح');
}
</script>
@endpush
@endsection 