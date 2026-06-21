<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ locale_dir() }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('pages.attendance_report_title') }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            margin: 24px;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
            text-align: center;
        }

        .meta {
            text-align: center;
            color: #666;
            margin-bottom: 18px;
        }

        .stats {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .stats th,
        .stats td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: center;
        }

        .stats th {
            background: #f3f4f6;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            border: 1px solid #ccc;
            padding: 5px 6px;
        }

        table.data th {
            background: #f3f4f6;
            font-weight: bold;
        }

        table.data tbody tr:nth-child(even) {
            background: #fafafa;
        }

        .summary {
            margin-top: 18px;
        }

        .summary h2 {
            font-size: 13px;
            margin: 0 0 8px;
        }

        .summary-grid {
            width: 100%;
        }

        .summary-grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 8px 0 0;
        }
    </style>
</head>
<body>
    <h1>{{ __('pages.attendance_report_title') }}</h1>
    <div class="meta">{{ now()->format('Y-m-d H:i') }}</div>

    <table class="stats">
        <thead>
            <tr>
                <th>{{ __('pages.total_students') }}</th>
                <th>{{ __('pages.total_lectures') }}</th>
                <th>{{ __('pages.total_present') }}</th>
                <th>{{ __('pages.total_absent') }}</th>
                <th>{{ __('pages.avg_attendance_rate') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $overallStats['total_users'] }}</td>
                <td>{{ $overallStats['total_sessions'] }}</td>
                <td>{{ $overallStats['total_attended'] }}</td>
                <td>{{ $overallStats['total_absent'] }}</td>
                <td>{{ number_format((float) $overallStats['average_attendance'], 1) }}%</td>
            </tr>
        </tbody>
    </table>

    @if($users->count() > 0)
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('pages.student_name') }}</th>
                    <th>{{ __('pages.phone_number') }}</th>
                    <th>{{ __('pages.total_sessions_count') }}</th>
                    <th>{{ __('pages.present_times') }}</th>
                    <th>{{ __('pages.absent_times') }}</th>
                    <th>{{ __('pages.late_times') }}</th>
                    <th>{{ __('pages.attendance_rate') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->first_name }} {{ $user->second_name }}</td>
                        <td>{{ $user->mobile_number }}</td>
                        <td>{{ $user->total_sessions }}</td>
                        <td>{{ $user->attended_sessions }}</td>
                        <td>{{ $user->absent_sessions }}</td>
                        <td>{{ $user->late_sessions }}</td>
                        <td>{{ number_format((float) $user->attendance_percentage, 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <table class="summary-grid">
                <tr>
                    <td>
                        <h2>{{ __('pages.top_5_students') }}</h2>
                        <ol>
                            @foreach($users->take(5) as $user)
                                <li>{{ $user->first_name }} {{ $user->second_name }} — {{ number_format((float) $user->attendance_percentage, 1) }}%</li>
                            @endforeach
                        </ol>
                    </td>
                    <td>
                        <h2>{{ __('pages.bottom_5_students') }}</h2>
                        <ol>
                            @foreach($users->sortBy('attendance_percentage')->take(5) as $user)
                                <li>{{ $user->first_name }} {{ $user->second_name }} — {{ number_format((float) $user->attendance_percentage, 1) }}%</li>
                            @endforeach
                        </ol>
                    </td>
                </tr>
            </table>
        </div>
    @else
        <p>{{ __('pages.no_students_registered') }}</p>
    @endif
</body>
</html>
