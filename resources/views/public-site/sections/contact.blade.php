@php
    use App\Support\PublicSite\ChurchPublicProfile;
    $profile = ChurchPublicProfile::fromSettings($church->settings ?? []);
@endphp
<section class="ps-section ps-section-alt">
    <div class="container">
        <h2>{{ __('public_site.contact') }}</h2>
        @if($content['use_profile'] ?? true)
            <ul class="list-unstyled mb-0">
                @if($profile['phone'] ?? null)<li>{{ __('public_site.phone') }}: {{ $profile['phone'] }}</li>@endif
                @if($profile['whatsapp'] ?? null)<li>WhatsApp: {{ $profile['whatsapp'] }}</li>@endif
                @if($profile['email'] ?? null)<li>{{ __('public_site.email') }}: <a href="mailto:{{ $profile['email'] }}">{{ $profile['email'] }}</a></li>@endif
            </ul>
        @endif
    </div>
</section>
