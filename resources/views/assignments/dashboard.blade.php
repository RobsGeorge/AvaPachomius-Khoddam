@extends('layouts.app')

@section('title', __('pages.assignments_dashboard'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="app-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="page-title mb-0">{{ __('pages.assignments_dashboard') }}</h2>
                    <a href="{{ route('assignments.create') }}" class="btn btn-primary">{{ __('pages.add_assignment') }}</a>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="row mb-4 g-3">
                        <div class="col-md-4">
                            <div class="app-tile h-100">
                                <h5 class="text-muted-theme">{{ __('pages.total_assignments') }}</h5>
                                <p class="display-6 mb-0">{{ $totalAssignments }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="app-tile h-100">
                                <h5 class="text-muted-theme">{{ __('pages.upcoming_assignments') }}</h5>
                                <p class="display-6 mb-0">{{ $upcomingAssignments }}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="app-tile h-100">
                                <h5 class="text-muted-theme">{{ __('pages.completed_assignments') }}</h5>
                                <p class="display-6 mb-0">{{ $completedAssignments }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="app-card card mb-4">
                        <div class="card-header">
                            <h3 class="page-title mb-0 h5">{{ __('pages.submission_status_report') }}</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('pages.assignment_name') }}</th>
                                            <th>{{ __('pages.due_date') }}</th>
                                            <th>{{ __('pages.students_submitted') }}</th>
                                            <th>{{ __('pages.students_not_submitted') }}</th>
                                            <th>{{ __('pages.students_graded') }}</th>
                                            <th>{{ __('pages.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($assignmentSummaries as $summary)
                                            <tr>
                                                <td>{{ $summary['assignment']->assignment_name }}</td>
                                                <td>{{ $summary['assignment']->due_date->format('Y-m-d H:i') }}</td>
                                                <td>{{ $summary['submitted'] }} / {{ $totalStudents }}</td>
                                                <td>{{ $summary['not_submitted'] }}</td>
                                                <td>{{ $summary['graded'] }}</td>
                                                <td>
                                                    <a href="{{ route('assignments.status', $summary['assignment']) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_status_report') }}</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted-theme">{{ __('pages.no_assignments') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="app-card card mb-4">
                        <div class="card-header">
                            <h3 class="page-title mb-0 h5">{{ __('pages.upcoming_assignments') }}</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('pages.assignment_name') }}</th>
                                            <th>{{ __('pages.due_date') }}</th>
                                            <th>{{ __('pages.total_points') }}</th>
                                            <th>{{ __('pages.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($upcomingAssignmentsList as $assignment)
                                            <tr>
                                                <td>{{ $assignment->assignment_name }}</td>
                                                <td>{{ $assignment->due_date->format('Y-m-d H:i') }}</td>
                                                <td>{{ $assignment->total_points }}</td>
                                                <td>
                                                    <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>
                                                    <a href="{{ route('assignments.status', $assignment) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_status_report') }}</a>
                                                    <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">{{ __('pages.edit') }}</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted-theme">{{ __('pages.no_upcoming_assignments') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="app-card card">
                        <div class="card-header">
                            <h3 class="page-title mb-0 h5">{{ __('pages.recent_submissions') }}</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('pages.student') }}</th>
                                            <th>{{ __('pages.assignments') }}</th>
                                            <th>{{ __('pages.submission_date') }}</th>
                                            <th>{{ __('pages.grade') }}</th>
                                            <th>{{ __('pages.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentSubmissions as $submission)
                                            <tr>
                                                <td>{{ $submission->user->displayName() }}</td>
                                                <td>{{ $submission->assignment->assignment_name }}</td>
                                                <td>{{ $submission->submitted_at->format('Y-m-d H:i') }}</td>
                                                <td>
                                                    @if($submission->points_earned !== null)
                                                        {{ $submission->points_earned }}/{{ $submission->assignment->total_points }}
                                                    @else
                                                        {{ __('pages.not_graded') }}
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('assignments.show', $submission->assignment) }}#submissions" class="btn btn-info btn-sm">{{ __('pages.view_submissions') }}</a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted-theme">{{ __('pages.no_recent_submissions') }}</td>
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
