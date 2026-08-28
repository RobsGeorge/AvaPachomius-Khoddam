@php
    $closesAt = $assessment->join_closes_at ?? null;
@endphp
@if($closesAt)
    @if($assessment->isJoinWindowOpen())
        <p class="small text-muted mb-2">
            <i class="bi bi-hourglass-split"></i>
            <span>{{ __('projects.join_closes_in') }}</span>
            <span class="fw-semibold" data-project-countdown="{{ $closesAt->toIso8601String() }}">
                {{ $closesAt->diffForHumans(['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
            </span>
            <span class="text-muted">({{ $closesAt->format('Y-m-d H:i') }})</span>
        </p>
    @else
        <p class="small mb-2">
            <span class="badge bg-secondary">{{ __('projects.join_window_closed_short') }}</span>
            <span class="text-muted">{{ $closesAt->format('Y-m-d H:i') }}</span>
        </p>
    @endif
@endif
