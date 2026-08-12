@php
    use App\Support\PublicSite\SectionTypes;
    $headline = SectionTypes::localized($content, 'headline');
    $sub = SectionTypes::localized($content, 'sub');
    $ctaLabel = SectionTypes::localized($content, 'cta_label');
    $image = ! empty($content['image_media_id']) ? \App\Models\ChurchMedia::find($content['image_media_id']) : null;
@endphp
<section class="ps-hero">
    @if($image)
        <div class="ps-hero-bg" style="background-image:url('{{ $image->publicUrl() }}');"></div>
        <div class="ps-hero-overlay"></div>
    @endif
    <div class="container ps-hero-inner">
        @if($headline)<h1>{{ $headline }}</h1>@endif
        @if($sub)<p>{{ $sub }}</p>@endif
        @if($ctaLabel && ! empty($content['cta_url']))
            <a class="btn-ps-primary mt-3" href="{{ $content['cta_url'] }}">{{ $ctaLabel }}</a>
        @endif
    </div>
</section>
