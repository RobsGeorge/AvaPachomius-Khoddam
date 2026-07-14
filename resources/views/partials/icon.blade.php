@php
    $raw = (string) ($icon ?? '');
    $extra = (string) ($class ?? '');
    $isFa = str_contains($raw, 'fa-')
        || str_starts_with($raw, 'fas ')
        || str_starts_with($raw, 'far ')
        || str_starts_with($raw, 'fab ')
        || str_starts_with($raw, 'fa ');
    $classes = trim('app-icon '.($isFa ? $raw : 'bi '.$raw).' '.$extra);
@endphp
<i class="{{ $classes }}" aria-hidden="true"></i>
