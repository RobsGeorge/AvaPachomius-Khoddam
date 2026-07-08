@if(!empty($profilePhotoWarning) && $profilePhotoDeadline)
    <div class="alert alert-warning profile-photo-banner mb-0 rounded-0 border-0 text-center">
        {{ __('pages.profile_photo_required_banner', ['deadline' => $profilePhotoDeadline->format('d/m/Y H:i')]) }}
        <a href="{{ route('profile') }}" class="alert-link fw-semibold">{{ __('pages.profile_photo_required_link') }}</a>
    </div>
@endif
