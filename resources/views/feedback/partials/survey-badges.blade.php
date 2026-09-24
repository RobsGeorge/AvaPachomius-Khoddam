@php
    /** @var \App\Models\FeedbackSurvey $survey */
    $anonymous = $survey->isAnonymous();
    $blocking = $survey->blocksResults();
    $blockedTitle = $survey->blockedAssessmentTitle();
@endphp
<div class="d-flex flex-wrap gap-1 {{ $class ?? 'mt-2' }}">
    <span class="badge {{ $anonymous ? 'bg-dark' : 'bg-info text-dark' }}">
        {{ $anonymous ? __('pages.feedback_badge_anonymous') : __('pages.feedback_badge_identified') }}
    </span>
    @if($blocking && $blockedTitle)
        <span class="badge bg-warning text-dark">
            {{ $survey->blocks_exam_id
                ? __('pages.feedback_badge_blocks_exam', ['name' => $blockedTitle])
                : __('pages.feedback_badge_blocks_project', ['name' => $blockedTitle]) }}
        </span>
    @elseif($blocking)
        <span class="badge bg-warning text-dark">{{ __('pages.feedback_badge_blocks_module') }}</span>
    @else
        <span class="badge bg-light text-dark border">{{ __('pages.feedback_badge_non_blocking') }}</span>
    @endif
</div>
