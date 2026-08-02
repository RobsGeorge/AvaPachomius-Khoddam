@if($student->profile_photo)
    @php
        $size = $size ?? 40;
        $actions = $gate->adminActions($student);
    @endphp
    <button type="button"
            class="btn p-0 border-0 student-photo-trigger"
            data-bs-toggle="modal"
            data-bs-target="#profilePhotoReviewModal"
            data-user-id="{{ $student->user_id }}"
            data-photo-url="{{ asset('storage/' . $student->profile_photo) }}"
            data-photo-name="{{ $student->displayName() }}"
            data-photo-email="{{ $student->email }}"
            data-can-approve-reject="{{ $actions['approve_reject'] ? '1' : '0' }}"
            data-can-extend="{{ $actions['extend_deadline'] ? '1' : '0' }}"
            data-can-reset="{{ $actions['reset_grace'] ? '1' : '0' }}"
            data-can-revoke="{{ $actions['revoke'] ? '1' : '0' }}"
            @if($actions['approve_reject'])
                data-approve-url="{{ route('admin.profile-photos.approve', $student) }}"
                data-reject-url="{{ route('admin.profile-photos.reject', $student) }}"
            @endif
            @if($actions['revoke'])
                data-revoke-url="{{ route('admin.profile-photos.revoke', $student) }}"
            @endif
            @if($actions['extend_deadline'])
                data-extend-url="{{ route('admin.profile-photos.extend-deadline', $student) }}"
            @endif
            @if($actions['reset_grace'])
                data-reset-url="{{ route('admin.profile-photos.reset-grace', $student) }}"
            @endif>
        <img src="{{ asset('storage/' . $student->profile_photo) }}"
             alt=""
             class="rounded-circle"
             width="{{ $size }}"
             height="{{ $size }}"
             style="object-fit:cover;">
    </button>
@endif
