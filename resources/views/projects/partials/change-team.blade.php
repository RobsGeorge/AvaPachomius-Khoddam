@php
    $membership = $membership ?? null;
    $assessment = $assessment ?? null;
    $changeUsed = $changeUsed ?? false;
    $joinWindowOpen = $joinWindowOpen ?? ($assessment?->isJoinWindowOpen() ?? false);
    $showForm = (bool) ($showForm ?? false);
@endphp
<div @if($showForm) id="change-team" @endif class="alert alert-warning border-warning mb-0">
    <div class="fw-semibold mb-1">
        <i class="bi bi-arrow-left-right"></i> {{ __('projects.change_team_here') }}
    </div>
    @if($showForm && $membership)
        @if($changeUsed)
            <p class="mb-0">{{ __('projects.change_chance_used') }}</p>
        @elseif(! $joinWindowOpen)
            <p class="mb-0">{{ __('projects.join_window_closed') }}</p>
        @else
            <p class="small mb-2">{{ __('projects.leave_team_help') }}</p>
            @error('project')
                <div class="alert alert-danger py-2 small mb-2">{{ $message }}</div>
            @enderror
            <form method="POST"
                  action="{{ route('projects.leave', $assessment) }}"
                  onsubmit="return confirm(@json(__('projects.leave_confirm')));">
                @csrf
                <button type="submit" class="btn btn-warning">{{ __('projects.leave_submit') }}</button>
            </form>
        @endif
    @else
        <p class="small mb-2">{{ __('projects.change_team_index_help') }}</p>
        @if(! empty($assignedProject))
            <a href="{{ route('projects.show', $assignedProject) }}#change-team" class="btn btn-sm btn-warning">
                {{ __('projects.change_team_open_page') }}
            </a>
        @endif
    @endif
</div>
