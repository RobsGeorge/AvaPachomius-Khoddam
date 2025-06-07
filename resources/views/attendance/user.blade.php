@extends('layouts.app')

@section('content')
<div class="container" dir="rtl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-right">سجل الحضور - {{ $user->first_name . ' ' . $user->second_name . ' ' . $user->third_name }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success mb-3">{{ session('success') }}</div>
    @endif

    @if($attendanceRecords->isEmpty())
        <p class="text-right">لا توجد سجلات حضور.</p>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المحاضرة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">سبب الإذن</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">تم التسجيل بواسطة</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">وقت التسجيل</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($attendanceRecords as $record)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                {{ $record->session->session_date }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                {{ $record->session->session_title }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @if($record->status === 'Present')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        حاضر
                                    </span>
                                @elseif($record->status === 'Absent')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        غائب
                                    </span>
                                @elseif($record->status === 'Late')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        متأخر
                                    </span>
                                @else
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ $record->status }}
                                    </span>
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

        <div class="mt-8">
            {{ $attendanceRecords->links() }}
        </div>
    @endif
</div>

@push('styles')
<style>
    /* Modern Pagination Styling */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin-top: 2rem;
        padding: 1rem;
        background-color: #ffffff;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .pagination > * {
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        background-color: #f8fafc;
        color: #1e293b;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
        border: 1px solid #e2e8f0;
        min-width: 2.5rem;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pagination > *:hover:not(.disabled) {
        background-color: #f1f5f9;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        border-color: #cbd5e1;
    }

    .pagination .active {
        background-color: #4f46e5;
        color: white;
        border-color: #4f46e5;
        font-weight: 600;
    }

    .pagination .active:hover {
        background-color: #4338ca;
    }

    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #f1f5f9;
    }

    /* Navigation arrows */
    .pagination .prev,
    .pagination .next {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        padding: 0.5rem 1.25rem;
    }

    .pagination .prev:before {
        content: "←";
        font-size: 1.25rem;
        line-height: 1;
    }

    .pagination .next:after {
        content: "→";
        font-size: 1.25rem;
        line-height: 1;
    }

    /* Page numbers */
    .pagination .page-item {
        min-width: 2.5rem;
        height: 2.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Responsive adjustments */
    @media (max-width: 640px) {
        .pagination {
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.75rem;
        }

        .pagination > * {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .pagination .prev,
        .pagination .next {
            padding: 0.375rem 1rem;
        }
    }
</style>
@endpush
@endsection 