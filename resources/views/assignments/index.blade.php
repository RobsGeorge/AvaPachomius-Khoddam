@extends('layouts.app')

@section('title', __('pages.assignments'))

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="app-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="page-title mb-0">{{ __('pages.assignments') }}</h2>
                    @if(Auth::user()->isInstructorOrAdmin())
                    <a href="{{ route('assignments.create') }}" class="btn btn-primary">{{ __('pages.add_assignment') }}</a>
                    @endif
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('pages.assignment_name') }}</th>
                                    <th>{{ __('pages.description') }}</th>
                                    <th>{{ __('pages.total_points') }}</th>
                                    <th>{{ __('pages.due_date') }}</th>
                                    @if(Auth::user()->isStudent())
                                        <th>{{ __('pages.submission_status') }}</th>
                                    @endif
                                    @if(Auth::user()->isInstructorOrAdmin())
                                        <th>{{ __('pages.submission_count') }}</th>
                                    @endif
                                    <th>{{ __('pages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $assignment)
                                    @php
                                        $submission = $studentSubmissions->get($assignment->assignment_id);
                                    @endphp
                                    <tr>
                                        <td>{{ $assignment->assignment_name }}</td>
                                        <td>{{ Str::limit($assignment->assignment_description, 500) }}</td>
                                        <td>{{ $assignment->total_points }}</td>
                                        <td>{{ $assignment->due_date->format('Y-m-d H:i') }}</td>
                                        @if(Auth::user()->isStudent())
                                            <td>
                                                @if($submission && $submission->points_earned !== null)
                                                    <span class="badge bg-success">{{ __('pages.status_graded') }} ({{ $submission->points_earned }}/{{ $assignment->total_points }})</span>
                                                @elseif($submission)
                                                    <span class="badge bg-primary">{{ __('pages.status_submitted') }}</span>
                                                @elseif(!$assignment->isSubmissionOpen())
                                                    <span class="badge bg-danger">{{ __('pages.status_overdue') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ __('pages.status_not_submitted') }}</span>
                                                @endif
                                            </td>
                                        @endif
                                        @if(Auth::user()->isInstructorOrAdmin())
                                            <td>{{ $submissionCounts->get($assignment->assignment_id, 0) }}</td>
                                        @endif
                                        <td class="d-flex flex-wrap gap-1">
                                            <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>

                                            @if(Auth::user()->isStudent())
                                                @if(!$submission && $assignment->isSubmissionOpen())
                                                    <a href="{{ route('assignments.show', $assignment) }}#submit" class="btn btn-primary btn-sm">{{ __('pages.submit_assignment') }}</a>
                                                @elseif($submission && $assignment->isSubmissionOpen())
                                                    <a href="{{ route('assignments.show', $assignment) }}#my-submission" class="btn btn-warning btn-sm">{{ __('pages.update_submission') }}</a>
                                                @endif
                                            @endif

                                            @if(Auth::user()->isInstructorOrAdmin())
                                                <a href="{{ route('assignments.status', $assignment) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_status_report') }}</a>
                                                <a href="{{ route('assignments.show', $assignment) }}#submissions" class="btn btn-outline-secondary btn-sm">{{ __('pages.view_submissions') }}</a>
                                                <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">{{ __('pages.edit') }}</a>
                                                <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm(@json(__('pages.confirm_delete_assignment')))">{{ __('pages.delete') }}</button>
                                                </form>
                                            @endif
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
        </div>
    </div>
</div>
@endsection
