@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-right">سجل حضور {{ $user->first_name . ' ' . $user->second_name . ' ' . $user->third_name }}</h1>
        <a href="{{ route('attendance.all') }}" class="btn btn-secondary">العودة إلى سجل الحضور الكامل</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    @if($attendanceRecords->isEmpty())
        <p class="text-right">لا توجد سجلات حضور لهذا المستخدم.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead>
                    <tr>
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