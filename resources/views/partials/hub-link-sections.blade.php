@php
    /** @var array<int, array{key: string, title: string, links: array<int, array<string, mixed>>}> $sections */
@endphp
@foreach($sections as $section)
    <h2 class="h6 text-muted-theme text-uppercase mb-2 {{ $loop->first ? 'mt-0' : 'mt-3' }}">{{ $section['title'] }}</h2>
    <div class="row g-3 mb-3">
        @foreach($section['links'] as $link)
            <div class="col-sm-6">
                <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ !empty($link['active']) ? 'hub-tile-active' : '' }}">
                    <h3 class="h5 mb-0">
                        @include('partials.icon', ['icon' => $link['icon']])
                        {{ $link['label'] }}
                    </h3>
                </a>
            </div>
        @endforeach
    </div>
@endforeach
