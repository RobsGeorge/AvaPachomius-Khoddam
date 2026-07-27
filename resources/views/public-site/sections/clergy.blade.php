@php
    $cards = ($content['pull_priests'] ?? true)
        ? \App\Models\Priest::query()->where('status', \App\Models\Priest::STATUS_ACTIVE)->limit(12)->get()
            ->map(fn ($p) => ['name' => $p->title, 'title' => ''])
        : collect($content['cards'] ?? [])->map(function ($c) {
            $name = app()->getLocale() === 'ar' ? ($c['name_ar'] ?? $c['name_en'] ?? '') : ($c['name_en'] ?? $c['name_ar'] ?? '');
            $title = app()->getLocale() === 'ar' ? ($c['title_ar'] ?? $c['title_en'] ?? '') : ($c['title_en'] ?? $c['title_ar'] ?? '');
            return ['name' => $name, 'title' => $title];
        });
@endphp
<section class="ps-section">
    <div class="container">
        <h2>{{ __('public_site.section_clergy') }}</h2>
        <div class="ps-cards">
            @forelse($cards as $card)
                <div class="ps-card">
                    <strong>{{ $card['name'] ?? '' }}</strong>
                    @if(! empty($card['title']))
                        <div class="text-muted small">{{ $card['title'] }}</div>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('public_site.clergy_empty') }}</p>
            @endforelse
        </div>
    </div>
</section>
