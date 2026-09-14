@php
    $text = trim((string) ($text ?? ''));
@endphp
@if($text !== '')
    <div class="alert alert-secondary mt-2 mb-0 py-2">
        <div class="fw-semibold small">{{ __('projects.previous_description') }}</div>
        <p class="small text-muted mb-1">{{ __('projects.previous_description_hint') }}</p>
        <p class="small mb-0" style="white-space: pre-wrap;">{{ $text }}</p>
    </div>
@endif
