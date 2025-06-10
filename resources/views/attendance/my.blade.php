@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">سجل الحضور الخاص بي</h1>

        @if($attendanceRecords->count() === 0)
            <div class="text-center py-8">
                <p class="text-gray-600">لا توجد سجلات حضور حتى الآن.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">المحاضرة</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">التاريخ</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">الحالة</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">سبب الاذن</th>
                            <th class="px-6 py-3 border-b text-right text-sm font-semibold text-gray-600">وقت التسجيل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendanceRecords as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 border-b text-sm text-gray-700">
                                    {{ $record->session->session_title ?? 'غير محدد' }}
                                </td>
                                <td class="px-6 py-4 border-b text-sm text-gray-700">
                                    {{ $record->session_date ?? 'غير محدد' }}
                                </td>
                                <td class="px-6 py-4 border-b text-sm">
                                     @if($record->status === 'Present')
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">حاضر</span>
                                    @elseif($record->status === 'Absent')
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full">غائب</span>
                                    @elseif($record->status === 'Permission')
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full">إذن</span>
                                    @else
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">متأخر</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->permission_reason }}
                                </td>
                                <td class="px-6 py-4 border-b text-sm text-gray-700">
                                    {{ $record->attendance_time ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">إحصائيات الحضور</h3>
                    @php
                        $total = $attendanceRecords->count();
                        $present = $attendanceRecords->where('status', 'Present')->count();
                        $absent = $attendanceRecords->where('status', 'Absent')->count();
                        $late = $attendanceRecords->where('status', 'Late')->count();
                        $presentPercentage = round(($present / $total) * 100);
                    @endphp
                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-600 mb-2">نسبة الحضور</p>
                            <div class="w-full bg-gray-200 rounded-full h-4">
                                <div class="bg-green-600 h-4 rounded-full" style="width: {{ $presentPercentage }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $presentPercentage }}%</p>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">{{ $present }}</p>
                                <p class="text-sm text-gray-600">حاضر</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">{{ $absent }}</p>
                                <p class="text-sm text-gray-600">غائب</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-yellow-600">{{ $late }}</p>
                                <p class="text-sm text-gray-600">متأخر</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">إحصائيات شهرية</h3>
                    @php
                        $monthlyStats = $attendanceRecords->groupBy(function($record) {
                            return \Carbon\Carbon::parse($record->session_date)->format('Y-m');
                        })->map(function($records) {
                            return [
                                'total' => $records->count(),
                                'present' => $records->where('status', 'Present')->count(),
                                'percentage' => round(($records->where('status', 'Present')->count() / $records->count()) * 100)
                            ];
                        })->sortKeysDesc();
                    @endphp
                    <div class="space-y-4">
                        @foreach($monthlyStats as $month => $stat)
                            <div class="border-b pb-2">
                                <p class="text-gray-800 font-medium">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</p>
                                <div class="flex justify-between items-center">
                                    <p class="text-sm text-gray-600">الحضور: {{ $stat['present'] }}/{{ $stat['total'] }}</p>
                                    <p class="text-sm text-gray-600">{{ round(($stat['present'] / $stat['total']) * 100) }}%</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection 