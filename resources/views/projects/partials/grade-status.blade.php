@php
    $visibility = $visibility ?? null;
    $grade = $visibility['grade'] ?? null;
    $canView = (bool) ($visibility['can_view'] ?? false);
    $reason = $visibility['reason'] ?? 'pending_announcement';
@endphp
@if($canView && $grade)
    <p class="mb-3">
        <span class="fw-semibold">{{ __('projects.your_grade') }}:</span>
        {{ number_format((float) $grade->points, 1) }}/{{ number_format((float) $assessment->max_points, 1) }}
        · {{ number_format((float) $grade->percent, 1) }}%
        <span class="badge {{ ($visibility['passed'] ?? false) ? 'bg-success' : 'bg-danger' }}">
            {{ ($visibility['passed'] ?? false) ? __('projects.passed') : __('projects.failed') }}
        </span>
    </p>
@elseif($canView)
    <p class="text-muted small mb-3">{{ __('projects.grade_not_entered') }}</p>
@elseif($reason === 'pending_feedback')
    <p class="text-muted small mb-3">{{ __('projects.score_pending_feedback') }}</p>
@else
    <p class="text-muted small mb-3">{{ __('projects.score_pending_announcement') }}</p>
@endif
