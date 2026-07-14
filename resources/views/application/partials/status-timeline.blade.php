@php
    use App\Models\RegistrationApplication;
    // Step 3 tone/label depends on the decision.
    $decisionState = match ($status) {
        RegistrationApplication::STATUS_APPROVED => ['done', 'success', 'bi-check-circle-fill', __('registration_review.timeline_decision_approved')],
        RegistrationApplication::STATUS_REJECTED => ['done', 'danger', 'bi-x-circle-fill', __('registration_review.timeline_decision_rejected')],
        RegistrationApplication::STATUS_NEEDS_CORRECTION => ['active', 'warning', 'bi-pencil-square', __('registration_review.timeline_decision_correction')],
        default => ['pending', 'secondary', 'bi-hourglass-split', __('registration_review.timeline_decision_pending')],
    };
    // Steps 1 & 2 are complete once submitted (any status here means it was submitted).
    $steps = [
        ['tone' => 'success', 'icon' => 'bi-check-circle-fill', 'title' => __('registration_review.timeline_submitted'), 'desc' => __('registration_review.timeline_submitted_desc')],
        ['tone' => $status === RegistrationApplication::STATUS_PENDING_REVIEW ? 'primary' : 'success', 'icon' => $status === RegistrationApplication::STATUS_PENDING_REVIEW ? 'bi-arrow-repeat' : 'bi-check-circle-fill', 'title' => __('registration_review.timeline_review'), 'desc' => __('registration_review.timeline_review_desc')],
        ['tone' => $decisionState[1], 'icon' => $decisionState[2], 'title' => __('registration_review.timeline_decision'), 'desc' => $decisionState[3]],
    ];
@endphp
<div class="app-card card shadow-sm mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-signpost-split" aria-hidden="true"></i> {{ __('registration_review.timeline_heading') }}
    </div>
    <div class="card-body">
        <ol class="list-unstyled mb-0 application-timeline">
            @foreach($steps as $step)
                <li class="d-flex gap-3 pb-3 position-relative">
                    <span class="badge rounded-circle bg-{{ $step['tone'] }} d-inline-flex align-items-center justify-content-center" style="width:2rem;height:2rem;">
                        <i class="bi {{ $step['icon'] }}" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="fw-semibold d-block">{{ $step['title'] }}</span>
                        <span class="text-muted-theme small">{{ $step['desc'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
