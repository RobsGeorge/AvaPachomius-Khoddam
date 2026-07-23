{{--
  Expects: $module, $course, $surveys (Collection), $submittedSurveyIds (optional),
           $canManageFeedback (bool), $variant ('student'|'admin')
--}}
@php
    $submittedSurveyIds = $submittedSurveyIds ?? collect();
    $canManageFeedback = $canManageFeedback ?? false;
    $variant = $variant ?? 'student';
    $isStudent = Auth::user()?->isStudent() ?? false;
@endphp

@if($surveys->isNotEmpty())
    <div class="{{ $variant === 'admin' ? 'mt-2' : 'px-3 py-2 border-bottom bg-light' }}">
        <small class="text-muted fw-semibold d-block mb-2">
            <i class="bi bi-chat-square-text me-1"></i>{{ __('pages.module_feedback_surveys') }}
        </small>
        <div class="d-flex flex-column gap-2">
            @foreach($surveys as $survey)
                @php
                    $submitted = isset($submittedSurveyIds[$survey->survey_id]);
                    $statusKey = 'pages.feedback_status_'.$survey->status;
                    $statusLabel = __($statusKey);
                    if ($statusLabel === $statusKey) {
                        $statusLabel = $survey->status;
                    }
                    $badgeClass = match ($survey->status) {
                        \App\Models\FeedbackSurvey::STATUS_OPEN => 'bg-warning text-dark',
                        \App\Models\FeedbackSurvey::STATUS_CLOSED => 'bg-secondary',
                        default => 'bg-light text-dark border',
                    };
                @endphp
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 min-w-0">
                        <span class="fw-semibold text-truncate">{{ $survey->title }}</span>
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                        @if($submitted)
                            <span class="badge bg-success">{{ __('pages.feedback_submitted') }}</span>
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        @if($canManageFeedback)
                            <a href="{{ route('feedback.surveys.edit', $survey) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> {{ __('pages.edit') }}
                            </a>
                            @if($survey->status !== \App\Models\FeedbackSurvey::STATUS_DRAFT)
                                <a href="{{ route('feedback.surveys.report', $survey) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-bar-chart"></i> {{ __('pages.feedback_report') }}
                                </a>
                            @endif
                        @elseif($isStudent && $survey->status !== \App\Models\FeedbackSurvey::STATUS_DRAFT)
                            @if($survey->status === \App\Models\FeedbackSurvey::STATUS_OPEN && ! $submitted)
                                <a href="{{ route('feedback.surveys.show', $survey) }}"
                                   class="btn btn-sm btn-warning">
                                    <i class="bi bi-chat-square-text"></i> {{ __('pages.give_feedback') }}
                                </a>
                            @else
                                <a href="{{ route('feedback.surveys.show', $survey) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i> {{ __('pages.view_feedback') }}
                                </a>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
