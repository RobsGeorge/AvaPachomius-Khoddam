@if(!empty($profilePhotoWarning) && $profilePhotoDeadline instanceof \Illuminate\Support\Carbon)
    <div class="alert alert-warning profile-photo-banner mb-0 rounded-0 border-0 text-center">
        {{ __('pages.profile_photo_required_banner', ['deadline' => $profilePhotoDeadline->format('d/m/Y H:i')]) }}
        <a href="{{ route('profile') }}" class="alert-link fw-semibold">{{ __('pages.profile_photo_required_link') }}</a>
    </div>
@endif

@if(!empty($profilePhotoPending))
    <div class="alert alert-info profile-photo-banner mb-0 rounded-0 border-0 text-center">
        {{ __('pages.profile_photo_pending_banner') }}
    </div>
@endif

@if(!empty($profilePhotoRejected))
    <div class="alert alert-danger profile-photo-banner mb-0 rounded-0 border-0 text-center">
        {{ __('pages.profile_photo_rejected_banner') }}
        @if(!empty($profilePhotoRejectionNote))
            <span class="d-block small mt-1">{{ $profilePhotoRejectionNote }}</span>
        @endif
        <a href="{{ route('profile') }}" class="alert-link fw-semibold">{{ __('pages.profile_photo_required_link') }}</a>
    </div>
@endif
