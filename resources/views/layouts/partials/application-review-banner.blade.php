@if(!empty($applicationReviewBanner))
    @php
        $bannerClass = match($applicationReviewBanner) {
            'correction' => 'alert-warning',
            'rejected' => 'alert-danger',
            default => 'alert-info',
        };
        $bannerText = match($applicationReviewBanner) {
            'correction' => __('registration_review.banner_correction'),
            'rejected' => __('registration_review.banner_rejected'),
            default => __('registration_review.banner_pending'),
        };
        $bannerLink = $applicationReviewBanner === 'correction'
            ? route('application.edit')
            : route('application.status');
        $bannerLinkText = $applicationReviewBanner === 'correction'
            ? __('registration_review.fix_application')
            : __('registration_review.view_status');
    @endphp
    <div class="alert {{ $bannerClass }} application-review-banner mb-0 rounded-0 border-0 text-center">
        {{ $bannerText }}
        <a href="{{ $bannerLink }}" class="alert-link ms-1">{{ $bannerLinkText }}</a>
    </div>
@endif
