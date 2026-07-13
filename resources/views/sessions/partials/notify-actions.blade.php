@php
    $sessionNotifier = app(\App\Services\SessionNotificationService::class);
    $isFutureSession = $sessionNotifier->isFutureSession($session);
@endphp

@if($canNotifySessions ?? false)
    <form method="POST" action="{{ route('sessions.toggle-notify', $session->session_id) }}" class="d-inline">
        @csrf
        @method('PATCH')
        <input type="hidden" name="notify_students" value="{{ $session->shouldNotifyStudents() ? 0 : 1 }}">
        <button type="submit"
                class="btn btn-sm {{ $session->shouldNotifyStudents() ? 'btn-outline-success' : 'btn-outline-secondary' }}"
                title="{{ __('pages.notify_students') }}">
            <i class="bi bi-bell{{ $session->shouldNotifyStudents() ? '-fill' : '' }}"></i>
        </button>
    </form>
    @if($isFutureSession && $session->shouldNotifyStudents())
        <form method="POST"
              action="{{ route('sessions.notify-students', $session->session_id) }}"
              class="d-inline"
              data-confirm="{{ __('pages.confirm_notify_session') }}"
              onsubmit="return confirm(this.dataset.confirm)">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-info" title="{{ __('pages.notify_session_students') }}">
                <i class="bi bi-send"></i>
            </button>
        </form>
    @endif
@endif
