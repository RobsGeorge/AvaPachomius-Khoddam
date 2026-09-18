@php
    $text = trim((string) ($text ?? ''));
@endphp
@if($text !== '')
    <div class="alert alert-secondary mt-2 mb-0 py-2">
        <label class="fw-semibold small mb-1" for="{{ $idPrefix ?? 'legacy' }}-previous-description">{{ __('projects.previous_description') }}</label>
        <p class="small text-muted mb-1">{{ __('projects.previous_description_hint') }}</p>
        <textarea id="{{ $idPrefix ?? 'legacy' }}-previous-description"
                  class="form-control form-control-sm bg-white"
                  rows="5"
                  readonly>{{ $text }}</textarea>
    </div>
@endif
