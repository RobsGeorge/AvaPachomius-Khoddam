@if(!empty($activeAnnouncementBanners) && $activeAnnouncementBanners->isNotEmpty())
    @foreach($activeAnnouncementBanners as $bannerItem)
        @php
            $announcement = $bannerItem['announcement'];
            $delivery = $bannerItem['delivery'];
            $locked = $announcement->hasChannel(\App\Models\Announcement::CHANNEL_BANNER_LOCKED);
        @endphp
        <div class="announcement-banner {{ $locked ? 'announcement-banner-locked' : 'announcement-banner-dismissible' }} mb-0 rounded-0 border-0">
            <div class="container py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <strong>{{ $announcement->title }}</strong>
                    <span class="d-block small">{!! nl2br(e(\Illuminate\Support\Str::limit($announcement->body, 180))) !!}</span>
                    <a href="{{ route('announcements.show', $announcement) }}" class="small fw-semibold">{{ __('pages.view_details') }}</a>
                </div>
                @if(! $locked)
                    <form method="POST" action="{{ route('announcements.dismiss-banner', $announcement) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-dark">{{ __('pages.close') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
@endif
