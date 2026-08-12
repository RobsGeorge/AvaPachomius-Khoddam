@php
    use App\Support\PublicSite\SectionTypes;
    $title = SectionTypes::localized($content, 'title');
    $body = SectionTypes::localized($content, 'body');
    $image = ! empty($content['image_media_id']) ? \App\Models\ChurchMedia::find($content['image_media_id']) : null;
@endphp
<section class="ps-section">
    <div class="container">
        @if($title)<h2>{{ $title }}</h2>@endif
        <div class="row align-items-center g-4">
            @if($image)
                <div class="col-md-5">
                    <img src="{{ $image->publicUrl() }}" alt="{{ $image->localizedAlt() ?? $title }}" class="img-fluid rounded">
                </div>
            @endif
            <div class="{{ $image ? 'col-md-7' : 'col-12' }}">
                @if($body)<p class="mb-0" style="white-space:pre-line;">{{ $body }}</p>@endif
            </div>
        </div>
    </div>
</section>
