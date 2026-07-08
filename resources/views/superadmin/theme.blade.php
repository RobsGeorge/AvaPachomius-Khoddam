@extends('layouts.app')

@section('title', __('pages.theme_colors_title'))

@section('content')
@php
    $colorFields = [
        'primary' => __('pages.theme_colors_primary'),
        'primary_hover' => __('pages.theme_colors_primary_hover'),
        'title' => __('pages.theme_colors_title_color'),
        'link' => __('pages.theme_colors_link'),
    ];
@endphp
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> {{ __('pages.back') }}
        </a>
        <h1 class="page-title mb-0">{{ __('pages.theme_colors_title') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-muted-theme mb-4">{{ __('pages.theme_colors_hint') }}</p>

    @if($publishedAt)
        <p class="small text-muted-theme mb-4">
            {{ __('pages.theme_colors_published_at', ['date' => $publishedAt->format('d/m/Y H:i')]) }}
            @if($publishedBy)
                {{ __('pages.theme_colors_published_by', ['name' => trim(($publishedBy->first_name ?? '') . ' ' . ($publishedBy->second_name ?? ''))]) }}
            @endif
        </p>
    @endif

    <form method="POST" action="{{ route('superadmin.theme.draft') }}" id="themeColorsForm" class="mb-4">
        @csrf

        <div class="row g-4">
            @foreach(['light' => __('pages.theme_colors_light_mode'), 'dark' => __('pages.theme_colors_dark_mode')] as $mode => $modeLabel)
                <div class="col-lg-6">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h2 class="h5 page-title mb-0">{{ $modeLabel }}</h2>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary theme-reset-mode"
                                        data-mode="{{ $mode }}">
                                    {{ __('pages.theme_colors_reset_mode') }}
                                </button>
                            </div>

                            @foreach($colorFields as $key => $label)
                                @php
                                    $value = old($mode . '.' . $key, $draft[$mode][$key] ?? $defaults[$mode][$key]);
                                @endphp
                                <div class="mb-3">
                                    <label class="form-label" for="theme-{{ $mode }}-{{ $key }}">{{ $label }}</label>
                                    <div class="input-group">
                                        <input type="color"
                                               class="form-control form-control-color theme-color-input"
                                               id="theme-{{ $mode }}-{{ $key }}"
                                               name="{{ $mode }}[{{ $key }}]"
                                               value="{{ $value }}"
                                               data-mode="{{ $mode }}"
                                               data-key="{{ $key }}">
                                        <input type="text"
                                               class="form-control font-monospace theme-color-hex"
                                               value="{{ $value }}"
                                               maxlength="7"
                                               pattern="^#[0-9A-Fa-f]{6}$"
                                               data-mode="{{ $mode }}"
                                               data-key="{{ $key }}"
                                               aria-label="{{ $label }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="app-card card mt-4">
            <div class="card-body">
                <h2 class="h6 page-title mb-3">{{ __('pages.theme_colors_preview_panel') }}</h2>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary" id="themePreviewBtn">
                        <i class="bi bi-eye"></i> {{ __('pages.theme_colors_preview') }}
                    </button>
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-save"></i> {{ __('pages.theme_colors_save_draft') }}
                    </button>
                    <button type="submit"
                            class="btn btn-primary"
                            formaction="{{ route('superadmin.theme.publish') }}"
                            data-confirm="{{ __('pages.theme_colors_publish_confirm') }}"
                            onclick="return confirm(this.dataset.confirm);">
                        <i class="bi bi-cloud-upload"></i> {{ __('pages.theme_colors_publish') }}
                    </button>
                    @if($hasDraft)
                        <button type="button"
                                class="btn btn-outline-danger ms-auto"
                                id="themeDiscardDraftBtn"
                                data-confirm="{{ __('pages.theme_colors_discard_confirm') }}">
                            {{ __('pages.theme_colors_discard_draft') }}
                        </button>
                    @endif
                </div>

                <div class="theme-preview-samples p-3 rounded border">
                    <p class="page-title h5 mb-2">{{ __('pages.theme_colors_title') }}</p>
                    <p class="mb-2">{{ __('pages.display_preferences_hint') }}</p>
                    <a href="#" class="text-decoration-none" onclick="return false;">{{ __('pages.theme_colors_link') }}</a>
                    <div class="mt-3">
                        <button type="button" class="btn btn-primary btn-sm">{{ __('pages.theme_colors_primary') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if($hasDraft)
        <form method="POST" action="{{ route('superadmin.theme.draft.discard') }}" id="themeDiscardDraftForm" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script type="application/json" id="theme-defaults-json">@json($defaults)</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaults = JSON.parse(document.getElementById('theme-defaults-json').textContent || '{}');
    const form = document.getElementById('themeColorsForm');

    function readPalette() {
        const palette = { light: {}, dark: {} };
        form.querySelectorAll('.theme-color-input').forEach((input) => {
            palette[input.dataset.mode][input.dataset.key] = input.value;
        });
        return palette;
    }

    function syncHexFromPicker(picker) {
        const hex = form.querySelector(`.theme-color-hex[data-mode="${picker.dataset.mode}"][data-key="${picker.dataset.key}"]`);
        if (hex) {
            hex.value = picker.value;
        }
    }

    function syncPickerFromHex(hexInput) {
        const value = hexInput.value.trim();
        if (!/^#[0-9A-Fa-f]{6}$/.test(value)) {
            return;
        }
        const picker = form.querySelector(`.theme-color-input[data-mode="${hexInput.dataset.mode}"][data-key="${hexInput.dataset.key}"]`);
        if (picker) {
            picker.value = value;
        }
    }

    form.querySelectorAll('.theme-color-input').forEach((input) => {
        input.addEventListener('input', () => syncHexFromPicker(input));
    });

    form.querySelectorAll('.theme-color-hex').forEach((input) => {
        input.addEventListener('change', () => syncPickerFromHex(input));
        input.addEventListener('blur', () => syncPickerFromHex(input));
    });

    document.getElementById('themePreviewBtn')?.addEventListener('click', () => {
        if (window.KhoddamUI?.applyThemePreview) {
            window.KhoddamUI.applyThemePreview(readPalette());
        }
    });

    form.addEventListener('submit', () => {
        form.querySelectorAll('.theme-color-hex').forEach((input) => syncPickerFromHex(input));
    });

    document.querySelectorAll('.theme-reset-mode').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.mode;
            const modeDefaults = defaults[mode] || {};
            Object.entries(modeDefaults).forEach(([key, value]) => {
                const picker = form.querySelector(`.theme-color-input[data-mode="${mode}"][data-key="${key}"]`);
                const hex = form.querySelector(`.theme-color-hex[data-mode="${mode}"][data-key="${key}"]`);
                if (picker) picker.value = value;
                if (hex) hex.value = value;
            });
        });
    });

    document.getElementById('themeDiscardDraftBtn')?.addEventListener('click', (event) => {
        const message = event.currentTarget.dataset.confirm || '';
        if (message && !confirm(message)) {
            return;
        }
        document.getElementById('themeDiscardDraftForm')?.submit();
    });
});
</script>
@endpush
