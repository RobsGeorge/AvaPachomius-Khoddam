@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">سجل الحضور الخاص بي</h1>

        @if($attendanceRecords->isEmpty())
            <div class="text-center py-8">
                <p class="text-gray-600">لا توجد سجلات حضور حتى الآن.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">الجلسة</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">التاريخ</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">الحالة</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">وقت التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendanceRecords as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 border-b text-sm text-gray-700">
                                    {{ $record->session_title ?? 'غير محدد' }}
                                </td>
                                <td class="px-6 py-4 border-b text-sm text-gray-700">
                                    {{ $record->session_date ? $record->session->date->format('Y-m-d') : 'غير محدد' }}
                                </td>
                                <td class="px-6 py-4 border-b text-sm">
                                    @if($record->status === 'Present')
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">حاضر</span>
                                    @elseif($record->status === 'Absent')
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full">غائب</span>
                                    @elseif($record->status === 'Permission')
                                        <span class="px-2 py-1 bg-yellow-100 text-blue-800 rounded-full">اذن</span>
                                    @else
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">متأخر</span>                                        
                                    @endif
                                </td>
                                <td class="px-6 py-4 border-b text-sm text-gray-700">
                                    @if($record->status !== 'Absent' && $record->created_at)
                                        {{ $record->attendance_time->format('H:i') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $attendanceRecords->links() }}
            </div>
        @endif
    </div>
</div>
@endsection 