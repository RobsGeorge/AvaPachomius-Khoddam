@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
    <h1 class="text-2xl font-bold mb-6 text-right">سجل الحضور الكامل</h1>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    <!-- Search and Filter Section -->
    <div class="bg-white p-4 rounded-lg shadow mb-6">
        <form action="{{ route('attendance.all') }}" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search by Name -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">البحث بالاسم</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        placeholder="ادخل اسم المستخدم">
                </div>

                <!-- Date Range -->
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">من تاريخ</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">إلى تاريخ</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">الحالة</label>
                    <select name="status" id="status"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">الكل</option>
                        <option value="Present" {{ request('status') == 'Present' ? 'selected' : '' }}>حاضر</option>
                        <option value="Absent" {{ request('status') == 'Absent' ? 'selected' : '' }}>غائب</option>
                        <option value="Late" {{ request('status') == 'Late' ? 'selected' : '' }}>متأخر</option>
                        <option value="Permission" {{ request('status') == 'Permission' ? 'selected' : '' }}>إذن</option>
                    </select>
                </div>

                <!-- Group By -->
                <div>
                    <label for="group_by" class="block text-sm font-medium text-gray-700 mb-1">تجميع حسب</label>
                    <select name="group_by" id="group_by"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">بدون تجميع</option>
                        <option value="date" {{ request('group_by') == 'date' ? 'selected' : '' }}>التاريخ</option>
                        <option value="user" {{ request('group_by') == 'user' ? 'selected' : '' }}>المستخدم</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">ترتيب حسب</label>
                    <select name="sort_by" id="sort_by"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="date_desc" {{ request('sort_by') == 'date_desc' ? 'selected' : '' }}>التاريخ (تنازلي)</option>
                        <option value="date_asc" {{ request('sort_by') == 'date_asc' ? 'selected' : '' }}>التاريخ (تصاعدي)</option>
                        <option value="name_asc" {{ request('sort_by') == 'name_asc' ? 'selected' : '' }}>الاسم (أ-ي)</option>
                        <option value="name_desc" {{ request('sort_by') == 'name_desc' ? 'selected' : '' }}>الاسم (ي-أ)</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-4 space-x-reverse">
                <button type="submit" class="btn btn-primary">بحث</button>
                <a href="{{ route('attendance.all') }}" class="btn btn-secondary">إعادة تعيين</a>
            </div>
        </form>
    </div>

    @if($attendanceRecords->isEmpty())
        <p class="text-right">لا توجد سجلات حضور.</p>
    @else
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

        <div class="mt-4">
            {{ $attendanceRecords->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection 