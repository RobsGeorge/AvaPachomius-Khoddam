@php
    $cards = collect($content['cards'] ?? [])->map(function ($c) {
        $title = app()->getLocale() === 'ar' ? ($c['title_ar'] ?? $c['title_en'] ?? '') : ($c['title_en'] ?? $c['title_ar'] ?? '');
        $body = app()->getLocale() === 'ar' ? ($c['body_ar'] ?? $c['body_en'] ?? '') : ($c['body_en'] ?? $c['body_ar'] ?? '');
        return ['title' => $title, 'body' => $body];
    })->filter(fn ($c) => filled($c['title']) || filled($c['body']));
@endphp
<section class="ps-section">
    <div class="container">
        <div class="ps-cards">
            @foreach($cards as $card)
                <div class="ps-card">
                    @if($card['title'])<h3 class="h5">{{ $card['title'] }}</h3>@endif
                    @if($card['body'])<p class="mb-0 small">{{ $card['body'] }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
