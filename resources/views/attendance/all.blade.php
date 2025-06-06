@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
    <h1 class="text-2xl font-bold mb-6 text-right">سجل الحضور الكامل</h1>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    @if($attendanceRecords->isEmpty())
        <p class="text-right">لا توجد سجلات حضور.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr>
                        <th class="px-6 py-3 border-b text-right">المستخدم</th>
                        <th class="px-6 py-3 border-b text-right">المحاضرة</th>
                        <th class="px-6 py-3 border-b text-right">التاريخ</th>
                        <th class="px-6 py-3 border-b text-right">الحالة</th>
                        <th class="px-6 py-3 border-b text-right">تم التسجيل بواسطة</th>
                        <th class="px-6 py-3 border-b text-right">وقت التسجيل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendanceRecords as $record)
                        <tr>
                            <td class="px-6 py-4 border-b text-right">
                                <a href="{{ route('attendance.user', $record->user_id) }}" class="text-blue-600 hover:underline">
                                    {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 border-b text-right">{{ $record->session->session_title }}</td>
                            <td class="px-6 py-4 border-b text-right">{{ $record->session->session_date }}</td>
                            <td class="px-6 py-4 border-b text-right">{{ $record->status }}</td>
                            <td class="px-6 py-4 border-b text-right">
                                {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                            </td>
                            <td class="px-6 py-4 border-b text-right">{{ $record->attendance_time }}</td>
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
@endsection 