@php
    $notifyEnabled = old('notify_students', isset($session) ? (bool) $session->notify_students : true);
    $selectedTargets = old(
        'notification_target_user_ids',
        isset($session) && $session->relationLoaded('notificationTargets')
            ? $session->notificationTargets->pluck('user_id')->all()
            : []
    );
@endphp

<div class="border rounded p-3 mb-4">
    <h2 class="h6 fw-semibold mb-3">{{ __('pages.notify_students') }}</h2>

    <div class="form-check form-switch mb-3">
        <input type="hidden" name="notify_students" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="notify_students" value="1"
               id="notify_students" @checked($notifyEnabled)>
        <label class="form-check-label" for="notify_students">{{ __('pages.notify_students') }}</label>
    </div>
    <p class="form-text text-muted-theme mb-3">{{ __('pages.notify_students_hint') }}</p>

    <div id="session-target-panel" @if(! $notifyEnabled) style="display:none" @endif>
        <label class="form-label fw-semibold" for="notification_target_user_ids">{{ __('pages.notify_students_targets') }}</label>
        <select name="notification_target_user_ids[]" id="notification_target_user_ids"
                class="form-select @error('notification_target_user_ids') is-invalid @enderror"
                multiple size="8">
            @foreach($rosterStudents as $student)
                <option value="{{ $student->user_id }}"
                    @selected(in_array($student->user_id, $selectedTargets, false))>
                    {{ $student->displayName() }}
                </option>
            @endforeach
        </select>
        <div class="form-text text-muted-theme">{{ __('pages.notify_students_targets_hint') }}</div>
        @error('notification_target_user_ids')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@push('scripts')
<script>
document.getElementById('notify_students')?.addEventListener('change', function () {
    const panel = document.getElementById('session-target-panel');
    if (panel) {
        panel.style.display = this.checked ? 'block' : 'none';
    }
});
</script>
@endpush
