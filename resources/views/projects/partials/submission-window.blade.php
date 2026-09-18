@php
    $dueAt = $assessment->submission_due_at ?? null;
    $graceAt = $assessment->graceEndsAt();
@endphp
@if($dueAt)
    @if($assessment->isSubmissionHardClosed())
        <div class="alert alert-danger py-2 small mb-2">
            {{ __('projects.submission_hard_closed') }}
            <div class="text-muted">{{ __('projects.grace_ends_on') }}: {{ $graceAt?->format('Y-m-d H:i') }}</div>
        </div>
    @elseif($assessment->isInLateGrace())
        <div class="alert alert-warning py-2 small mb-2">
            {{ __('projects.late_grace_alert') }}
            <div class="text-muted">
                {{ __('projects.submission_due_on') }}: {{ $dueAt->format('Y-m-d H:i') }}
                · {{ __('projects.grace_ends_on') }}: {{ $graceAt?->format('Y-m-d H:i') }}
            </div>
        </div>
    @else
        <div class="alert alert-info py-2 small mb-2">
            {{ __('projects.late_penalty_alert') }}
            <div class="text-muted">
                {{ __('projects.submission_due_on') }}: {{ $dueAt->format('Y-m-d H:i') }}
                @if($graceAt)
                    · {{ __('projects.grace_ends_on') }}: {{ $graceAt->format('Y-m-d H:i') }}
                @endif
            </div>
        </div>
    @endif
@endif
