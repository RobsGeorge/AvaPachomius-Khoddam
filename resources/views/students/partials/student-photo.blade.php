@props(['student'])

@if($student->profile_photo)
    <div class="student-roster-photo flex-shrink-0">
        <button type="button"
                class="btn p-0 border-0 student-photo-trigger"
                data-bs-toggle="modal"
                data-bs-target="#studentPhotoModal"
                data-photo-url="{{ asset('storage/' . $student->profile_photo) }}"
                data-photo-name="{{ $student->displayName() }}"
                aria-label="{{ __('pages.view_photo') }}: {{ $student->displayName() }}">
            <img src="{{ asset('storage/' . $student->profile_photo) }}"
                 alt="{{ $student->displayName() }}"
                 class="rounded-circle object-fit-cover">
        </button>
    </div>
@endif
