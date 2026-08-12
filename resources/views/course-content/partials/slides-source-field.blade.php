@php
    $slidesSource = old('slides_source', $lecture->slides_media_id ? 'hosted_file' : 'external_link');
@endphp

<div class="mb-3" data-curriculum-slides-source>
    <label class="form-label fw-semibold">{{ __('pages.slides_url') }} / PDF</label>

    <div class="btn-group btn-group-sm mb-2" role="group" aria-label="{{ __('curriculum.slides_source') }}">
        <input type="radio" class="btn-check" name="slides_source" id="slides_source_link" value="external_link"
               @checked($slidesSource === 'external_link') autocomplete="off">
        <label class="btn btn-outline-secondary" for="slides_source_link">
            <i class="bi bi-link-45deg"></i> {{ __('curriculum.source_external_link') }}
        </label>
        <input type="radio" class="btn-check" name="slides_source" id="slides_source_file" value="hosted_file"
               @checked($slidesSource === 'hosted_file') autocomplete="off">
        <label class="btn btn-outline-secondary" for="slides_source_file">
            <i class="bi bi-cloud-upload"></i> {{ __('curriculum.source_hosted_file') }}
        </label>
    </div>

    <div data-slides-panel="external_link" @class(['d-none' => $slidesSource !== 'external_link'])>
        <input type="url" name="slides_link" class="form-control"
               value="{{ old('slides_link', $lecture->slides_link) }}" maxlength="500"
               placeholder="https://...">
        <div class="form-text">{{ __('curriculum.slides_link_hint') }}</div>
    </div>

    <div data-slides-panel="hosted_file" @class(['d-none' => $slidesSource !== 'hosted_file'])>
        @if($lecture->slidesMedia)
            <div class="alert alert-light border small py-2 mb-2 d-flex align-items-center justify-content-between gap-2">
                <span>
                    <i class="bi bi-file-earmark-arrow-down text-primary"></i>
                    {{ $lecture->slidesMedia->original_filename }}
                    <span class="text-muted">({{ \App\Services\StorageFormat::bytes($lecture->slidesMedia->size_bytes) }})</span>
                </span>
                <a href="{{ $lecture->slidesMedia->downloadUrl() }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                    {{ __('curriculum.preview_file') }}
                </a>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="remove_slides_file" value="1" id="remove_slides_file">
                <label class="form-check-label small" for="remove_slides_file">{{ __('curriculum.remove_current_file') }}</label>
            </div>
        @endif
        <input type="file" name="slides_file" class="form-control"
               accept="{{ '.' . implode(',.', $allowedMimes ?? config('curriculum.allowed_mimes')) }}">
        <div class="form-text">{{ __('curriculum.upload_hint', ['max' => $maxUploadMb ?? 20, 'types' => implode(', ', $allowedMimes ?? config('curriculum.allowed_mimes'))]) }}</div>
        @error('slides_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-curriculum-slides-source]');
    if (!root) return;
    const panels = root.querySelectorAll('[data-slides-panel]');
    root.querySelectorAll('input[name="slides_source"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            panels.forEach(function (panel) {
                panel.classList.toggle('d-none', panel.getAttribute('data-slides-panel') !== radio.value);
            });
        });
    });
});
</script>
