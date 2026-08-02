@php
    $compact = $compact ?? true;
    $actions = $gate->adminActions($student);
    $hasActions = $actions['approve_reject'] || $actions['extend_deadline'] || $actions['reset_grace'];
@endphp
@if(! $hasActions)
    <span class="small text-muted-theme">{{ __('profile_photos.no_actions_for_status') }}</span>
@else
<div class="d-flex flex-column gap-2">
    @if($actions['approve_reject'])
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.profile-photos.approve', $student) }}" @class(['d-inline' => $compact, 'w-100' => ! $compact])>
                @csrf
                <button type="submit" @class(['btn', 'btn-sm', 'btn-success', 'w-100' => ! $compact])>
                    <i class="bi bi-check-lg"></i> {{ __('profile_photos.approve') }}
                </button>
            </form>
        </div>
        <form method="POST" action="{{ route('admin.profile-photos.reject', $student) }}"
              data-confirm="{{ __('profile_photos.confirm_reject') }}">
            @csrf
            <input type="text" name="profile_photo_rejection_note" class="form-control form-control-sm mb-1"
                   placeholder="{{ __('profile_photos.rejection_note') }}">
            <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                <i class="bi bi-x-lg"></i> {{ __('profile_photos.reject') }}
            </button>
        </form>
    @endif

    @if($actions['extend_deadline'])
        <form method="POST" action="{{ route('admin.profile-photos.extend-deadline', $student) }}"
              @class(['d-flex gap-1 flex-wrap' => $compact, 'd-flex flex-column gap-1' => ! $compact])>
            @csrf
            <input type="datetime-local" name="profile_photo_deadline_at" class="form-control form-control-sm" required>
            <button type="submit" @class(['btn', 'btn-sm', 'btn-outline-primary', 'w-100' => ! $compact])>
                {{ __('profile_photos.extend_deadline') }}
            </button>
        </form>
    @endif

    @if($actions['reset_grace'])
        <form method="POST" action="{{ route('admin.profile-photos.reset-grace', $student) }}"
              data-confirm="{{ __('profile_photos.confirm_reset_grace') }}">
            @csrf
            <button type="submit" @class(['btn', 'btn-sm', 'btn-outline-warning', 'w-100' => ! $compact])>
                {{ __('profile_photos.reset_grace') }}
            </button>
        </form>
    @endif
</div>
@endif
