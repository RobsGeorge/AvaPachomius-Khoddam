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
            <div class="overflow-x-auto">
                <div class="inline-block min-w-full align-middle">
                    <div class="overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المستخدم</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المحاضرة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">سبب الإذن</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تم التسجيل بواسطة</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">وقت التسجيل</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($attendanceRecords as $record)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            <a href="{{ route('attendance.user', $record->user_id) }}" class="text-blue-600 hover:underline">
                                                {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ $record->session->session_title }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ $record->session_date }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            @if($record->status === 'Present')
                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">حاضر</span>
                                            @elseif($record->status === 'Absent')
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full">غائب</span>
                                            @else
                                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">متأخر</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ $record->permission_reason }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ $record->attendance_time }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                {{ $attendanceRecords->links() }}
            </div>
        @endif
    </div>
</div>
@endsection 