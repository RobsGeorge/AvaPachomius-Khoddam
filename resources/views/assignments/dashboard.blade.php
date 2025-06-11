@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">لوحة إدارة الواجبات</h2>
                    <a href="{{ route('assignments.create') }}" class="btn btn-primary">إضافة واجب جديد</a>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">إجمالي الواجبات</h5>
                                    <p class="card-text display-4">{{ $totalAssignments }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">الواجبات القادمة</h5>
                                    <p class="card-text display-4">{{ $upcomingAssignments }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">الواجبات المكتملة</h5>
                                    <p class="card-text display-4">{{ $completedAssignments }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Assignments -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="mb-0">الواجبات القادمة</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>اسم الواجب</th>
                                            <th>تاريخ التسليم</th>
                                            <th>الدرجة الكلية</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($upcomingAssignmentsList as $assignment)
                                            <tr>
                                                <td>{{ $assignment->assignment_name }}</td>
                                                <td>{{ $assignment->due_date->format('Y-m-d H:i') }}</td>
                                                <td>{{ $assignment->total_points }}</td>
                                                <td>
                                                    <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">عرض</a>
                                                    <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">تعديل</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center">لا توجد واجبات قادمة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Submissions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0">آخر التسليمات</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>الطالب</th>
                                            <th>الواجب</th>
                                            <th>تاريخ التسليم</th>
                                            <th>الدرجة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentSubmissions as $submission)
                                            <tr>
                                                <td>{{ $submission->user->name }}</td>
                                                <td>{{ $submission->assignment->assignment_name }}</td>
                                                <td>{{ $submission->submitted_at->format('Y-m-d H:i') }}</td>
                                                <td>
                                                    @if($submission->points_earned !== null)
                                                        {{ $submission->points_earned }}/{{ $submission->assignment->total_points }}
                                                    @else
                                                        لم يتم التقييم
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('assignments.show', $submission->assignment) }}" class="btn btn-info btn-sm">عرض</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center">لا توجد تسليمات حديثة</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 