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
            @foreach($records as $record)
                <tr class="attendance-row" 
                    data-name="{{ strtolower($record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name) }}"
                    data-date="{{ $record->session->session_date }}"
                    data-status="{{ $record->status }}">
                    <td class="px-6 py-4 border-b text-right">
                        <a href="{{ route('attendance.user', $record->user_id) }}" class="text-blue-600 hover:underline">
                            {{ $record->user->first_name . ' ' . $record->user->second_name . ' ' . $record->user->third_name }}
                        </a>
                    </td>
                    <td class="px-6 py-4 border-b text-right">{{ $record->session->session_title }}</td>
                    <td class="px-6 py-4 border-b text-right">{{ $record->session->session_date }}</td>
                    <td class="px-6 py-4 border-b text-right">
                        <span class="px-2 py-1 rounded-full text-sm
                            @if($record->status == 'Present') bg-green-100 text-green-800
                            @elseif($record->status == 'Absent') bg-red-100 text-red-800
                            @elseif($record->status == 'Late') bg-yellow-100 text-yellow-800
                            @else bg-blue-100 text-blue-800
                            @endif">
                            {{ $record->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 border-b text-right">
                        {{ $record->takenBy->first_name . ' ' . $record->takenBy->second_name }}
                    </td>
                    <td class="px-6 py-4 border-b text-right">{{ $record->attendance_time }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div> 