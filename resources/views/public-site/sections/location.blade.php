@php
    use App\Support\PublicSite\ChurchPublicProfile;
    $profile = ChurchPublicProfile::fromSettings($church->settings ?? []);
    $address = trim(($profile['address'] ?? '').(($profile['city'] ?? '') ? ' — '.$profile['city'] : ''));
    $mapUrl = $content['map_embed_url'] ?? '';
@endphp
<section class="ps-section">
    <div class="container">
        <h2>{{ __('public_site.address') }}</h2>
        @if(($content['use_profile'] ?? true) && $address)
            <p>{{ $address }}</p>
        @endif
        @if($mapUrl && str_starts_with(strtolower($mapUrl), 'https://'))
            <div class="ratio ratio-16x9 mt-3">
                <iframe src="{{ $mapUrl }}" title="{{ __('public_site.address') }}" loading="lazy" referrerpolicy="no-referrer"></iframe>
            </div>
        @endif
    </div>
</section>
