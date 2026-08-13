{{-- Khoddam "Orbit" mark: static cross + sweeping gold ring. Pure SVG; colours via CSS vars. --}}
@props(['size' => null, 'label' => null])
<svg {{ $attributes->merge(['class' => 'kh-orbit']) }}
     @if($size) style="--kh-orbit-size: {{ $size }};" @endif
     viewBox="0 0 100 100" role="img" aria-label="{{ $label ?? __('app.loading') }}">
    <g class="kh-orbit__cross">
        <polygon points="44,50 56,50 60,26 40,26" />
        <polygon points="44,50 56,50 60,74 40,74" />
        <polygon points="50,44 50,56 26,60 26,40" />
        <polygon points="50,44 50,56 74,60 74,40" />
    </g>
    <circle class="kh-orbit__ring" cx="50" cy="50" r="44" fill="none"
            stroke-width="5" stroke-linecap="round" stroke-dasharray="70 210" />
</svg>
