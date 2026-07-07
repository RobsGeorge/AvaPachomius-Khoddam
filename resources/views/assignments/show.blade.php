@extends('layouts.app')

@section('title', $assignment->assignment_name)

@section('content')
<div class="container animate-in">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="app-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="page-title mb-0">{{ $assignment->assignment_name }}</h2>
                    @if(Auth::user()->isInstructorOrAdmin())
                    <div>
                        <a href="{{ route('assignments.edit', $assignment) }}" class="btn btn-warning">{{ __('pages.edit') }}</a>
                        <form action="{{ route('assignments.destroy', $assignment) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" onclick="return confirm(@json(__('pages.confirm_delete_assignment')))">{{ __('pages.delete') }}</button>
                        </form>
                    </div>
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

                    <div class="mb-4">
                        <h4>{{ __('pages.description') }}</h4>
                        <p>{{ $assignment->assignment_description }}</p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4>{{ __('pages.total_points') }}</h4>
                            <p>{{ $assignment->total_points }}</p>
                        </div>
                        <div class="col-md-6">
                            <h4>{{ __('pages.due_date') }}</h4>
                            <p>{{ $assignment->due_date->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    @if($assignment->instructions)
                    <div class="mb-4">
                        <h4>{{ __('pages.instructions') }}</h4>
                        <p>{{ $assignment->instructions }}</p>
                    </div>
                    @endif

                    @if($assignment->resources)
                    <div class="mb-4">
                        <h4>{{ __('pages.resources') }}</h4>
                        <p>{{ $assignment->resources }}</p>
                    </div>
                    @endif

                    @if(Auth::user()->isStudent())
                        <div class="mb-4">
                            <h4>{{ __('pages.your_submission_status') }}</h4>
                            @if($studentStatus === 'graded')
                                <span class="badge bg-success fs-6">{{ __('pages.status_graded') }} ({{ $currentSubmission->points_earned }}/{{ $assignment->total_points }})</span>
                            @elseif($studentStatus === 'submitted')
                                <span class="badge bg-primary fs-6">{{ __('pages.status_submitted') }}</span>
                            @elseif($studentStatus === 'not_submitted')
                                <span class="badge bg-secondary fs-6">{{ __('pages.status_not_submitted') }}</span>
                            @else
                                <span class="badge bg-danger fs-6">{{ __('pages.status_overdue') }}</span>
                            @endif
                        </div>

                        <div class="alert alert-info mb-4">
                            <h5 class="alert-heading mb-2">
                                <i class="fas fa-info-circle me-1"></i>
                                {{ __('pages.upload_requirements_title') }}
                            </h5>
                            <p class="mb-0">{{ __('pages.upload_requirements_body', ['max' => \App\Models\Assignment::MAX_UPLOAD_MB]) }}</p>
                        </div>

                        @if($canSubmit)
                    <div class="mb-4" id="submit">
                        <h4>{{ __('pages.submit_assignment') }}</h4>
                        <form action="{{ route('assignments.submit', $assignment) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="submission_content">{{ __('pages.content_label') }}</label>
                                <textarea class="form-control @error('submission_content') is-invalid @enderror" 
                                          id="submission_content" name="submission_content" rows="5" required>{{ old('submission_content') }}</textarea>
                                @error('submission_content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="file">{{ __('pages.attachment_pdf') }} <span class="text-danger">*</span></label>
                                <input type="file" class="form-control @error('file') is-invalid @enderror" 
                                       id="file" name="file" accept="application/pdf,.pdf" required>
                                <small class="form-text text-muted-theme">{{ __('pages.pdf_max_size') }}</small>
                                @error('file')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('pages.submit_assignment') }}</button>
                        </form>
                    </div>
                        @elseif(!$currentSubmission && !$submissionOpen)
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                {{ __('pages.deadline_passed', ['date' => $assignment->due_date->addHours(3)->format('Y-m-d H:i')]) }}
                            </div>
                        @endif

                    <div class="mb-4" id="my-submission">
                        <h4>{{ __('pages.my_submissions') }}</h4>
                            @if($currentSubmission)
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">{{ $currentSubmission->user->displayName() }}</h5>
                                        <span class="badge bg-info">{{ $currentSubmission->submitted_at->addHours(3)->format('Y-m-d H:i') }}</span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="fw-bold">{{ __('pages.content_label') }}:</h6>
                                            <p class="card-text">{{ $currentSubmission->submission_content }}</p>
                                    </div>

                                        @if($currentSubmission->file_path)
                                        <div class="mb-3">
                                            <h6 class="fw-bold">{{ __('pages.file_attachment') }}</h6>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                    <a href="{{ Storage::url($currentSubmission->file_path) }}" 
                                                   target="_blank" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i>
                                                    {{ __('pages.download_file') }}
                                                </a>
                                                    <a href="{{ Storage::url($currentSubmission->file_path) }}" 
                                                   target="_blank" 
                                                   class="btn btn-outline-info btn-sm ms-2">
                                                    <i class="fas fa-eye me-1"></i>
                                                    {{ __('pages.view_file') }}
                                                </a>
                                            </div>
                                        </div>
                                    @endif

                                        @if($currentSubmission->points_earned !== null)
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                        <label class="form-label">{{ __('pages.grade') }}</label>
                                                        <p class="form-control-static">{{ $currentSubmission->points_earned }} / {{ $assignment->total_points }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                        <label class="form-label">{{ __('pages.feedback_title') }}</label>
                                                        <p class="form-control-static">{{ $currentSubmission->feedback ?? __('pages.no_feedback') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        @if($submissionOpen)
                                            <div class="mt-3">
                                                <h6 class="fw-bold">{{ __('pages.update_submission') }}</h6>
                                                <form action="{{ route('assignments.update-submission', $currentSubmission) }}" method="POST" enctype="multipart/form-data">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="form-group mb-3">
                                                        <label for="submission_content">{{ __('pages.content_label') }}</label>
                                                        <textarea class="form-control @error('submission_content') is-invalid @enderror" 
                                                                  id="submission_content" 
                                                                  name="submission_content" 
                                                                  rows="5" 
                                                                  required>{{ old('submission_content', $currentSubmission->submission_content) }}</textarea>
                                                        @error('submission_content')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>

                                                    <div class="form-group mb-3">
                                                        <label for="file">{{ __('pages.attachment_pdf') }}</label>
                                                        <input type="file" 
                                                               class="form-control @error('file') is-invalid @enderror" 
                                                               id="file" 
                                                               name="file" 
                                                               accept="application/pdf,.pdf">
                                                        <small class="form-text text-muted-theme">{{ __('pages.optional_resubmit_extended') }}</small>
                                                        @error('file')
                                                            <div class="invalid-feedback">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-1"></i>
                                                        {{ __('pages.update_submission') }}
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <div class="alert alert-warning mt-3">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                {{ __('pages.deadline_passed', ['date' => $assignment->due_date->addHours(3)->format('Y-m-d H:i')]) }}
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            @else
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                {{ __('pages.no_submissions_yet') }}
                            </div>
                        @endif
                    </div>
                    @endif
                    

                    @if(Auth::user()->isInstructorOrAdmin())
                    <div class="mb-4" id="submissions">
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                            <h4 class="mb-0">{{ __('pages.submissions') }} ({{ $submissions->count() }})</h4>
                            <a href="{{ route('assignments.status', $assignment) }}" class="btn btn-outline-primary btn-sm">{{ __('pages.view_status_report') }}</a>
                        </div>
                        @forelse($submissions as $submission)
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="card-title mb-0">{{ $submission->user->displayName() }}</h5>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($submission->points_earned !== null)
                                                <span class="badge bg-success">{{ __('pages.status_graded') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('pages.not_graded') }}</span>
                                            @endif
                                            <span class="badge bg-info">{{ $submission->submitted_at->addHours(3)->format('Y-m-d H:i') }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6 class="fw-bold">{{ __('pages.content_label') }}:</h6>
                                        <p class="card-text">{{ $submission->submission_content }}</p>
                                    </div>

                                    @if($submission->file_path)
                                        <div class="mb-3">
                                            <h6 class="fw-bold">{{ __('pages.file_attachment') }}</h6>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                                <a href="{{ Storage::url($submission->file_path) }}" 
                                                   target="_blank" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-download me-1"></i>
                                                    {{ __('pages.download_file') }}
                                                </a>
                                                <a href="{{ Storage::url($submission->file_path) }}" 
                                                   target="_blank" 
                                                   class="btn btn-outline-info btn-sm ms-2">
                                                    <i class="fas fa-eye me-1"></i>
                                                    {{ __('pages.view_file') }}
                                                </a>
                                            </div>
                                        </div>
                                    @endif

                                    <form action="{{ route('assignments.grade', $submission) }}" method="POST" class="mt-3">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="points_earned" class="form-label">{{ __('pages.grade') }}</label>
                                                    <input type="number" 
                                                           class="form-control @error('points_earned') is-invalid @enderror" 
                                                           id="points_earned" 
                                                           name="points_earned" 
                                                           value="{{ old('points_earned', $submission->points_earned) }}" 
                                                           min="0" 
                                                           max="{{ $assignment->total_points }}">
                                                    @error('points_earned')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="feedback" class="form-label">{{ __('pages.feedback_title') }}</label>
                                                    <textarea class="form-control @error('feedback') is-invalid @enderror" 
                                                              id="feedback" 
                                                              name="feedback" 
                                                              rows="3">{{ old('feedback', $submission->feedback) }}</textarea>
                                                    @error('feedback')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check me-1"></i>
                                            {{ __('pages.grade_action') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                {{ __('pages.no_submissions_yet') }}
                            </div>
                        @endforelse
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: @json(__('pages.select_team_members')),
            allowClear: true,
            language: {
                noResults: function() {
                    return @json(__('pages.no_results'));
                },
                searching: function() {
                    return @json(__('pages.searching'));
                }
            },
            templateResult: formatUser,
            templateSelection: formatUserSelection,
            escapeMarkup: function(markup) {
                return markup;
            }
        });
    });

    function formatUser(user) {
        if (!user.id) {
            return user.text;
        }
        return $('<span>' + user.text + '</span>');
    }

    function formatUserSelection(user) {
        if (!user.id) {
            return user.text;
        }
        return $('<span>' + user.text + '</span>');
    }
</script>
@endpush 