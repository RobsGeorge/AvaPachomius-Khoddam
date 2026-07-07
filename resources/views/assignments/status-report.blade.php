@extends('layouts.app')

@section('title', __('pages.submission_status_report'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="app-card card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="page-title mb-1">{{ __('pages.submission_status_report') }}</h2>
                        <p class="text-muted-theme mb-0">{{ $assignment->assignment_name }} — {{ __('pages.due_date') }}: {{ $assignment->due_date->format('Y-m-d H:i') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-outline-theme">{{ __('pages.back_to_assignment') }}</a>
                        <a href="{{ route('assignments.show', $assignment) }}#submissions" class="btn btn-primary">{{ __('pages.view_submissions') }}</a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row mb-4 g-3">
                        <div class="col-md-3 col-6">
                            <div class="app-tile h-100 text-center">
                                <h6 class="text-muted-theme">{{ __('pages.total_students') }}</h6>
                                <p class="display-6 mb-0">{{ $stats['total'] }}</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="app-tile h-100 text-center">
                                <h6 class="text-muted-theme">{{ __('pages.students_submitted') }}</h6>
                                <p class="display-6 mb-0 text-primary">{{ $stats['submitted'] }}</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="app-tile h-100 text-center">
                                <h6 class="text-muted-theme">{{ __('pages.students_not_submitted') }}</h6>
                                <p class="display-6 mb-0 text-warning">{{ $stats['not_submitted'] }}</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="app-tile h-100 text-center">
                                <h6 class="text-muted-theme">{{ __('pages.students_overdue') }}</h6>
                                <p class="display-6 mb-0 text-danger">{{ $stats['overdue'] }}</p>
                            </div>
                        </div>
                    </div>

                    @if($stats['not_submitted'] > 0 && $assignment->isSubmissionOpen())
                        <div class="alert alert-warning">
                            <i class="fas fa-bell me-2"></i>
                            {{ __('pages.alert_late_students') }}: <strong>{{ $stats['not_submitted'] }}</strong>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('pages.student') }}</th>
                                    <th>{{ __('pages.email') }}</th>
                                    <th>{{ __('pages.mobile') }}</th>
                                    <th>{{ __('pages.submission_status') }}</th>
                                    <th>{{ __('pages.submission_date') }}</th>
                                    <th>{{ __('pages.grade') }}</th>
                                    <th>{{ __('pages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    @php
                                        $student = $row['student'];
                                        $submission = $row['submission'];
                                        $status = $row['status'];
                                    @endphp
                                    <tr>
                                        <td>{{ $student->displayName() }}</td>
                                        <td>{{ $student->email ?? '—' }}</td>
                                        <td>{{ $student->mobile_number ?? '—' }}</td>
                                        <td>
                                            @if($status === 'graded')
                                                <span class="badge bg-success">{{ __('pages.status_graded') }}</span>
                                            @elseif($status === 'submitted')
                                                <span class="badge bg-primary">{{ __('pages.status_submitted') }}</span>
                                            @elseif($status === 'not_submitted')
                                                <span class="badge bg-warning text-dark">{{ __('pages.status_pending') }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ __('pages.status_overdue') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($submission)
                                                {{ $submission->submitted_at->format('Y-m-d H:i') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($submission && $submission->points_earned !== null)
                                                {{ $submission->points_earned }}/{{ $assignment->total_points }}
                                            @else
                                                {{ __('pages.not_graded') }}
                                            @endif
                                        </td>
                                        <td>
                                            @if($submission)
                                                <a href="{{ route('assignments.show', $assignment) }}#submissions" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>
                                            @elseif($student->email)
                                                <a href="mailto:{{ $student->email }}" class="btn btn-outline-primary btn-sm">{{ __('pages.contact') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted-theme">{{ __('pages.no_assignments') }}</td>
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
@endsection
