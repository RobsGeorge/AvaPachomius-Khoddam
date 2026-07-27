@php
    $ids = array_map('intval', $content['media_ids'] ?? []);
    $items = $ids ? \App\Models\ChurchMedia::query()->whereIn('church_media_id', $ids)->get()->keyBy('church_media_id') : collect();
@endphp
<section class="ps-section ps-section-alt">
    <div class="container">
        <h2>{{ __('public_site.section_gallery') }}</h2>
        @if($items->isNotEmpty())
            <div class="ps-gallery-grid">
                @foreach($ids as $id)
                    @if($items->has($id))
                        <img src="{{ $items[$id]->publicUrl() }}" alt="{{ $items[$id]->localizedAlt() ?? '' }}">
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-muted mb-0">{{ __('public_site.gallery_empty') }}</p>
        @endif
    </div>
</section>
