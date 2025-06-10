@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">سجل الحضور - {{ $user->first_name . ' ' . $user->second_name . ' ' . $user->third_name }}</h1>

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
                                    {{ $record->session->session_title }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->session_date }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    @if($record->status === 'Present')
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full">حاضر</span>
                                    @elseif($record->status === 'Absent')
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full">غائب</span>
                                    @else
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full">متأخر</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900 whitespace-nowrap">
                                    {{ $record->permission_reason }}
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
                <!-- Overall Statistics -->
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

                <!-- Monthly Statistics -->
                <div class="bg-white p-6 rounded-lg shadow-md col-span-2">
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
                        @foreach($monthlyStats as $stat)
                            <div class="border-b pb-2">
                                <p class="text-gray-800 font-medium">{{ \Carbon\Carbon::createFromFormat('Y-m', $stat->month)->format('F Y') }}</p>
                                <div class="flex justify-between items-center">
                                    <p class="text-sm text-gray-600">الحضور: {{ $stat->present }}/{{ $stat->total }}</p>
                                    <p class="text-sm text-gray-600">{{ round(($stat->present / $stat->total) * 100) }}%</p>
                                </div>
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
@endsection 