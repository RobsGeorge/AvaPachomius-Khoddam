@extends('layouts.app')

@section('title', __('pages.profile_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h2 class="page-title mb-4">{{ __('pages.profile_title') }}</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!empty($photoGateBlocked))
        <div class="alert alert-danger">
            {{ __('pages.profile_photo_required_locked') }}
        </div>
    @elseif($photoDeadline)
        <div class="alert alert-warning">
            {{ __('pages.profile_photo_required_banner', ['deadline' => $photoDeadline->format('d/m/Y H:i')]) }}
        </div>
    @endif

    @if(!empty($photoPendingReview))
        <div class="alert alert-info">{{ __('pages.profile_photo_pending_banner') }}</div>
    @endif

    @if(!empty($photoRejected))
        <div class="alert alert-danger">
            {{ __('pages.profile_photo_rejected_banner') }}
            @if($photoRejectionNote)
                <span class="d-block small mt-1">{{ $photoRejectionNote }}</span>
            @endif
        </div>
    @endif

    <div class="app-card card mb-4">
        <div class="card-body text-center">
            <div class="profile-photo-wrap mx-auto mb-4">
                @if($user->profile_photo)
                    <button type="button"
                            class="btn p-0 border-0 profile-photo-trigger"
                            data-bs-toggle="modal"
                            data-bs-target="#profilePhotoModal"
                            aria-label="{{ __('pages.view_photo') }}">
                        <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ __('pages.profile_photo') }}"
                            class="profile-photo-img rounded-circle">
                    </button>
                @else
                    <div class="profile-photo-placeholder rounded-circle d-flex align-items-center justify-content-center">
                        <span class="text-muted-theme">{{ __('pages.no_photo') }}</span>
                    </div>
                @endif

                <form action="{{ route('profile.picture.update') }}" method="POST" enctype="multipart/form-data" id="profilePhotoUploadForm">
                    @csrf
                    @method('PUT')
                    <label for="profile_photo_input" class="btn btn-sm btn-primary mt-3">
                        <i class="bi bi-camera"></i> {{ __('pages.upload_new_photo') }}
                    </label>
                    <input type="file" name="profile_photo" id="profile_photo_input" class="d-none" accept="image/*"
                           onchange="this.form.submit()">
                </form>
            </div>

            <div class="text-start">
                <p><strong>{{ __('pages.full_name') }}:</strong> {{ $fullName }}</p>
                <p><strong>{{ __('pages.birth_date') }}:</strong>
                    {{ $user->date_of_birth?->format('Y-m-d') ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.national_id') }}:</strong> {{ $user->national_id ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.phone') }}:</strong> 0{{ $user->mobile_number ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.job') }}:</strong> {{ $user->job ?? __('pages.not_available') }}</p>
                <p><strong>{{ __('pages.email') }}:</strong> {{ $user->email }}</p>
                <p><strong>{{ __('pages.registration_date') }}:</strong>
                    {{ $user->formattedRegistrationDate() }}</p>
            </div>
        </div>
    </div>

    <div class="app-card card text-center">
        <div class="card-body">
            <h3 class="page-title h5 mb-4">{{ __('pages.qr_attendance_title') }}</h3>
            <div class="d-inline-block p-4 bg-white border rounded shadow qr-attendance-trigger"
                 role="button"
                 tabindex="0"
                 data-bs-toggle="modal"
                 data-bs-target="#qrModal"
                 aria-label="{{ __('pages.scan_qr_attendance') }}">
                {!! QrCode::size(200)->generate($attendanceUrl) !!}
            </div>
            <p class="mt-2 text-muted-theme small">{{ __('pages.scan_qr_attendance') }}</p>
        </div>
    </div>
</div>
@endsection

@push('modals')
@if($user->profile_photo)
<div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-labelledby="profilePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="profilePhotoModalLabel">{{ __('pages.profile_photo_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
            </div>
            <div class="modal-body text-center">
                <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="{{ $fullName }}"
                     class="img-fluid rounded-circle student-photo-modal-image">
            </div>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalLabel">{{ __('pages.qr_attendance_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
            </div>
            <div class="modal-body text-center">
                {!! QrCode::size(280)->generate($attendanceUrl) !!}
                <p class="mt-3 mb-0 text-muted-theme small">{{ __('pages.scan_qr_attendance') }}</p>
            </div>
        </div>
    </div>
</div>

@if(!empty($photoGateBlocked) && ! $user->profile_photo)
<div class="modal fade" id="photoRequiredModal" tabindex="-1" aria-hidden="true" data-show-on-load="1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pages.profile_photo_required_locked') }}</h5>
            </div>
            <div class="modal-body text-center">
                <p class="mb-3">{{ __('pages.upload_new_photo') }}</p>
                <label for="profile_photo_modal_input" class="btn btn-primary">
                    <i class="bi bi-camera"></i> {{ __('pages.profile_photo_required_link') }}
                </label>
                <input type="file" id="profile_photo_modal_input" class="d-none" accept="image/*"
                       onchange="document.getElementById('profile_photo_input').files = this.files; document.getElementById('profilePhotoUploadForm').submit();">
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const requiredModal = document.getElementById('photoRequiredModal');
    if (requiredModal?.dataset.showOnLoad === '1') {
        bootstrap.Modal.getOrCreateInstance(requiredModal, { backdrop: 'static', keyboard: false }).show();
    }
});
</script>
@endpush
