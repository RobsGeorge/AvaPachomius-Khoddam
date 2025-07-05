@extends('layouts.app')

@section('content')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap');

    .report-container {
        font-family: 'Cairo', sans-serif;
        text-align: right;
        direction: rtl;
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
        color: #333;
    }

    .report-title {
        font-weight: 900;
        font-size: 2.5rem;
        margin-bottom: 2rem;
        color: #1a202c;
        text-align: center;
    }

    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        text-align: center;
        border-left: 5px solid #667eea;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 900;
        color: #667eea;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 1rem;
        color: #718096;
        font-weight: 600;
    }

    .report-table {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .report-table table {
        width: 100%;
        border-collapse: collapse;
    }

    .report-table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
        font-weight: 700;
        text-align: center;
        border: none;
    }

    .report-table td {
        padding: 1rem;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
        vertical-align: middle;
    }

    .report-table tr:hover {
        background-color: #f7fafc;
    }

    .user-name-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 700;
        transition: color 0.3s ease;
    }

    .user-name-link:hover {
        color: #5a67d8;
        text-decoration: underline;
    }

    .attendance-percentage {
        font-weight: 700;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.875rem;
    }

    .percentage-excellent {
        background: #c6f6d5;
        color: #22543d;
    }

    .percentage-good {
        background: #fef5e7;
        color: #744210;
    }

    .percentage-average {
        background: #fed7d7;
        color: #742a2a;
    }

    .percentage-poor {
        background: #fed7d7;
        color: #742a2a;
    }

    .btn-back {
        background: #718096;
        color: white;
        text-decoration: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        background: #4a5568;
        color: white;
    }

    .no-data {
        text-align: center;
        padding: 3rem;
        color: #718096;
        font-size: 1.2rem;
    }

    .export-buttons {
        margin-bottom: 1rem;
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }

    .btn-export {
        background: #48bb78;
        color: white;
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.3s ease;
    }

    .btn-export:hover {
        background: #38a169;
        color: white;
    }
</style>

<div class="report-container">
    <a href="{{ route('dashboard') }}" class="btn-back">
        <i class="fas fa-arrow-right"></i> العودة إلى لوحة التحكم
    </a>

    <h1 class="report-title">تقرير الحضور والغياب الإجمالي</h1>

    <!-- Overall Statistics Cards -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-number">{{ $overallStats['total_users'] }}</div>
            <div class="stat-label">إجمالي الطلاب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $overallStats['total_sessions'] }}</div>
            <div class="stat-label">إجمالي الجلسات</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $overallStats['total_attended'] }}</div>
            <div class="stat-label">إجمالي الحضور</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $overallStats['total_absent'] }}</div>
            <div class="stat-label">إجمالي الغياب</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ number_format($overallStats['average_attendance'], 1) }}%</div>
            <div class="stat-label">متوسط نسبة الحضور</div>
        </div>
    </div>

    <!-- Export Buttons -->
    <div class="export-buttons">
        <a href="{{ route('attendance.report') }}?export=pdf" class="btn-export">
            <i class="fas fa-file-pdf"></i> تصدير PDF
        </a>
        <a href="{{ route('attendance.report') }}?export=excel" class="btn-export">
            <i class="fas fa-file-excel"></i> تصدير Excel
        </a>
    </div>

    <!-- Users Table -->
    <div class="report-table">
        @if($users->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>اسم الطالب</th>
                        <th>البريد الإلكتروني</th>
                        <th>إجمالي الجلسات</th>
                        <th>جلسات الحضور</th>
                        <th>جلسات الغياب</th>
                        <th>جلسات التأخير</th>
                        <th>نسبة الحضور</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>
                                <a href="{{ route('attendance.user', $user->user_id) }}" class="user-name-link">
                                    {{ $user->first_name }} {{ $user->second_name }}
                                </a>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->total_sessions }}</td>
                            <td>
                                <span class="text-success font-weight-bold">{{ $user->attended_sessions }}</span>
                            </td>
                            <td>
                                <span class="text-danger font-weight-bold">{{ $user->absent_sessions }}</span>
                            </td>
                            <td>
                                <span class="text-warning font-weight-bold">{{ $user->late_sessions }}</span>
                            </td>
                            <td>
                                @php
                                    $percentage = $user->attendance_percentage;
                                    $percentageClass = 'percentage-poor';
                                    if ($percentage >= 90) {
                                        $percentageClass = 'percentage-excellent';
                                    } elseif ($percentage >= 75) {
                                        $percentageClass = 'percentage-good';
                                    } elseif ($percentage >= 60) {
                                        $percentageClass = 'percentage-average';
                                    }
                                @endphp
                                <span class="attendance-percentage {{ $percentageClass }}">
                                    {{ number_format($percentage, 1) }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="no-data">
                <i class="fas fa-users fa-3x mb-3"></i>
                <p>لا يوجد طلاب مسجلين حالياً</p>
            </div>
        @endif
    </div>

    <!-- Summary -->
    @if($users->count() > 0)
        <div class="mt-4">
            <h3 class="text-center mb-3">ملخص التقرير</h3>
            <div class="row">
                <div class="col-md-6">
                    <h5>أفضل 5 طلاب من حيث الحضور:</h5>
                    <ul class="list-group">
                        @foreach($users->take(5) as $index => $user)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $index + 1 }}. {{ $user->first_name }} {{ $user->second_name }}</span>
                                <span class="badge badge-success">{{ number_format($user->attendance_percentage, 1) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>أدنى 5 طلاب من حيث الحضور:</h5>
                    <ul class="list-group">
                        @foreach($users->reverse()->take(5) as $index => $user)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>{{ $index + 1 }}. {{ $user->first_name }} {{ $user->second_name }}</span>
                                <span class="badge badge-danger">{{ number_format($user->attendance_percentage, 1) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection 