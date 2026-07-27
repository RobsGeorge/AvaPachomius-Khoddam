@php
    use App\Support\PublicSite\SectionTypes;
    $text = SectionTypes::localized($content, 'text');
    $citation = SectionTypes::localized($content, 'citation');
@endphp
<section class="ps-section ps-section-alt">
    <div class="container">
        <blockquote class="ps-quote mb-0">
            @if($text)<p class="mb-2">{{ $text }}</p>@endif
            @if($citation)<footer class="small">— {{ $citation }}</footer>@endif
        </blockquote>
    </div>
</section>
