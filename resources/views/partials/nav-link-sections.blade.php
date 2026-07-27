@php
    /** @var array<int, array{key: string, title: string, links: array<int, array<string, mixed>>}> $sections */
    $mobile = $mobile ?? false;
@endphp
@foreach($sections as $section)
    @if($mobile)
        <div class="small text-muted-theme text-uppercase px-2 pt-2">{{ $section['title'] }}</div>
        @foreach($section['links'] as $link)
            <a href="{{ $link['url'] }}" class="app-nav-link small {{ !empty($link['active']) ? 'active' : '' }}" @click="navOpen = false">
                @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-1']){{ $link['label'] }}
            </a>
        @endforeach
    @else
        <li><h6 class="dropdown-header">{{ $section['title'] }}</h6></li>
        @foreach($section['links'] as $link)
            <li>
                <a class="dropdown-item app-dropdown-link {{ !empty($link['active']) ? 'active fw-semibold' : '' }}"
                   href="{{ $link['url'] }}">
                    @include('partials.icon', ['icon' => $link['icon'], 'class' => 'me-2']){{ $link['label'] }}
                </a>
            </li>
        @endforeach
        @if(! $loop->last)
            <li><hr class="dropdown-divider"></li>
        @endif
    @endif
@endforeach
