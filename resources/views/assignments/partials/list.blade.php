@if(Auth::user()->isStudent())
    <div class="student-data-hub">
        @forelse($assignments as $assignment)
            @php
                $submission = $studentSubmissions->get($assignment->assignment_id);
            @endphp
            <article class="data-card">
                <div class="data-card-title">{{ $assignment->assignment_name }}</div>
                <dl class="data-meta-list mb-0">
                    <div class="data-meta-row">
                        <dt>{{ __('pages.description') }}</dt>
                        <dd>{{ Str::limit($assignment->assignment_description, 500) }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('pages.total_points') }}</dt>
                        <dd>{{ $assignment->total_points }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('pages.due_date') }}</dt>
                        <dd>{{ $assignment->due_date->format('Y-m-d H:i') }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('pages.submission_status') }}</dt>
                        <dd>
                            @if($submission && $submission->points_earned !== null)
                                <span class="badge bg-success">{{ __('pages.status_graded') }} ({{ $submission->points_earned }}/{{ $assignment->total_points }})</span>
                            @elseif($submission)
                                <span class="badge bg-primary">{{ __('pages.status_submitted') }}</span>
                            @elseif(!$assignment->isSubmissionOpen())
                                <span class="badge bg-danger">{{ __('pages.status_overdue') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('pages.status_not_submitted') }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>
                <div class="data-card-actions">
                    <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>
                    @if(!$submission && $assignment->isSubmissionOpen())
                        <a href="{{ route('assignments.show', $assignment) }}#submit" class="btn btn-primary btn-sm">{{ __('pages.submit_assignment') }}</a>
                    @elseif($submission && $assignment->isSubmissionOpen())
                        <a href="{{ route('assignments.show', $assignment) }}#my-submission" class="btn btn-warning btn-sm">{{ __('pages.update_submission') }}</a>
                    @endif
                </div>
            </article>
        @empty
            <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_assignments') }}</p>
        @endforelse
    </div>
@else
    <div class="table-responsive d-none d-lg-block admin-table-desktop">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>{{ __('pages.assignment_name') }}</th>
                    <th>{{ __('pages.description') }}</th>
                    <th>{{ __('pages.total_points') }}</th>
                    <th>{{ __('pages.due_date') }}</th>
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
                        @if(Auth::user()->isInstructorOrAdmin())
                            <td>{{ $submissionCounts->get($assignment->assignment_id, 0) }}</td>
                        @endif
                        <td class="d-flex flex-wrap gap-1">
                            <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>

                            @if(Auth::user()->isInstructorOrAdmin())
                                <a href="{{ route('assignments.status', $assignment) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_status_report') }}</a>
                                <a href="{{ route('assignments.show', $assignment) }}#submissions" class="btn btn-outline-secondary btn-sm">{{ __('pages.view_submissions') }}</a>
                                <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">{{ __('pages.edit') }}</a>
                                <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="d-inline"
                                      data-confirm="{{ __('pages.confirm_delete_assignment') }}"
                                      onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('pages.delete') }}</button>
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

    <div class="d-lg-none admin-data-cards student-data-hub">
        @forelse($assignments as $assignment)
            @php $submission = $studentSubmissions->get($assignment->assignment_id); @endphp
            <article class="data-card">
                <div class="data-card-title">{{ $assignment->assignment_name }}</div>
                <dl class="data-meta-list mb-3">
                    <div class="data-meta-row">
                        <dt>{{ __('pages.description') }}</dt>
                        <dd>{{ Str::limit($assignment->assignment_description, 500) }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('pages.total_points') }}</dt>
                        <dd>{{ $assignment->total_points }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('pages.due_date') }}</dt>
                        <dd>{{ $assignment->due_date->format('Y-m-d H:i') }}</dd>
                    </div>
                    <div class="data-meta-row">
                        <dt>{{ __('pages.submission_count') }}</dt>
                        <dd>{{ $submissionCounts->get($assignment->assignment_id, 0) }}</dd>
                    </div>
                </dl>
                <div class="data-card-actions d-flex flex-wrap gap-2">
                    <a href="{{ route('assignments.show', $assignment) }}" class="btn btn-info btn-sm">{{ __('pages.view') }}</a>
                    <a href="{{ route('assignments.status', $assignment) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_status_report') }}</a>
                    <a href="{{ route('assignments.show', $assignment) }}#submissions" class="btn btn-outline-secondary btn-sm">{{ __('pages.view_submissions') }}</a>
                    <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning btn-sm">{{ __('pages.edit') }}</a>
                    <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="w-100"
                          data-confirm="{{ __('pages.confirm_delete_assignment') }}"
                          onsubmit="return confirm(this.dataset.confirm)">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm w-100">{{ __('pages.delete') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_assignments') }}</p>
        @endforelse
    </div>
@endif
