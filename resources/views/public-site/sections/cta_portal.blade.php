@php
    use App\Support\PublicSite\SectionTypes;
    $headline = SectionTypes::localized($content, 'headline');
    $sub = SectionTypes::localized($content, 'sub');
@endphp
<section class="ps-section">
    <div class="container text-center">
        @if($headline)<h2>{{ $headline }}</h2>@endif
        @if($sub)<p class="text-muted">{{ $sub }}</p>@endif
        <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
            <a class="btn-ps-primary" href="{{ route('login') }}">{{ __('public_site.login') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('register') }}">{{ __('public_site.register') }}</a>
        </div>
    </div>
</section>
