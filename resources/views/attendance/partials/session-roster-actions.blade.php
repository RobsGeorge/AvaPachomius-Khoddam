@php
    $session = $session ?? null;
    $roster = $roster ?? ['missing' => 0];
@endphp

@if($session)
    <div class="px-3 py-3 border-bottom d-flex flex-wrap align-items-center gap-2">
        @if(($roster['missing'] ?? 0) > 0)
            <form method="POST" action="{{ route('sessions.attendance.fill-missing', $session->session_id) }}"
                  data-confirm="{{ __('pages.confirm_fill_missing_attendance') }}">
                @csrf
                <input type="hidden" name="status" value="Absent">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-person-x"></i> {{ __('pages.fill_missing_as_absent') }}
                </button>
            </form>
        @endif

        <button type="button"
                class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#add-session-attendance-modal">
            <i class="bi bi-person-plus"></i> {{ __('pages.add_student_attendance') }}
        </button>
    </div>

    @include('attendance.partials.session-roster-add-modal', ['session' => $session])
@endif
