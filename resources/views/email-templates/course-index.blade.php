@extends('layouts.app')

@section('title', __('email_templates.hub_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:980px;" id="email-template-hub"
     data-preview-url="{{ route('courses.email-templates.preview', $course) }}"
     data-edit-locale="{{ $editLocale }}">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1 h4">{{ __('email_templates.hub_title') }}</h1>
            <p class="text-muted-theme small mb-0">{{ $course->localizedTitle() }} — {{ __('email_templates.hub_intro') }}</p>
        </div>
        <a href="{{ route('hubs.academic') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    <form method="GET" action="{{ route('courses.email-templates.index', $course) }}" class="app-card card shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
            <label class="small fw-semibold mb-0" for="edit-locale">{{ __('email_templates.edit_language') }}</label>
            <select name="locale" id="edit-locale" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                @foreach($locales as $locale)
                    <option value="{{ $locale }}" @selected($editLocale === $locale)>{{ __('email_templates.locale_'.$locale) }}</option>
                @endforeach
            </select>
            <span class="text-muted-theme small">{{ __('email_templates.compact_hint') }}</span>
        </div>
    </form>

    @foreach($families as $family)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-header py-2 fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-envelope-paper me-1"></i>{{ $family['label'] }}</span>
                <span class="small text-muted-theme">{{ __('email_templates.placeholders') }}:
                    @foreach($family['placeholders'] as $ph)
                        <code class="small">@php echo e('{{'.$ph.'}}'); @endphp</code>@if(! $loop->last), @endif
                    @endforeach
                </span>
            </div>
            <div class="card-body py-2">
                <form method="POST" action="{{ route('courses.email-templates.update', $course) }}" class="email-template-family-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="family" value="{{ $family['family'] }}">
                    <input type="hidden" name="edit_locale" value="{{ $editLocale }}">

                    @foreach($family['keys'] as $templateKey)
                        @php
                            $byLocale = $family['templates']->get($templateKey, collect());
                            $template = $byLocale->get($editLocale) ?? $byLocale->first();
                            $defaultLocale = $family['defaults'][$templateKey] ?? 'ar';
                            if ($family['family'] === \App\Services\EmailTemplateCatalog::FAMILY_COURSE_APPLICATION) {
                                $label = __('course_applications.email_subjects.'.$templateKey);
                            } elseif ($family['family'] === \App\Services\EmailTemplateCatalog::FAMILY_COURSE_GRADUATION) {
                                $label = __('course_graduation.email_subjects.'.$templateKey);
                            } else {
                                $label = $templateKey;
                            }
                        @endphp
                        @continue(! $template)

                        <details class="email-template-panel mb-2" @if($loop->first) open @endif>
                            <summary class="email-template-summary d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <span class="fw-semibold small">{{ $label }}</span>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border">{{ strtoupper($editLocale) }}</span>
                            </summary>
                            <div class="pt-2 px-1">
                                <div class="row g-2 mb-2 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1">{{ __('email_templates.subject') }}</label>
                                        <input type="text"
                                               name="templates[{{ $template->id }}][subject]"
                                               class="form-control form-control-sm email-tpl-subject"
                                               value="{{ old('templates.'.$template->id.'.subject', $template->subject) }}"
                                               required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">{{ __('email_templates.default_send_language') }}</label>
                                        <select name="defaults[{{ $templateKey }}]" class="form-select form-select-sm">
                                            @foreach($locales as $locale)
                                                <option value="{{ $locale }}" @selected($defaultLocale === $locale)>{{ __('email_templates.locale_'.$locale) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button"
                                                class="btn btn-outline-theme btn-sm w-100 email-tpl-preview-btn"
                                                data-family="{{ $family['family'] }}">
                                            <i class="bi bi-eye"></i> {{ __('email_templates.preview') }}
                                        </button>
                                    </div>
                                </div>
                                <label class="form-label small mb-1">{{ __('email_templates.body') }}</label>
                                <textarea name="templates[{{ $template->id }}][body_html]"
                                          class="form-control email-tpl-editor"
                                          rows="6"
                                          data-tinymce="1">{{ old('templates.'.$template->id.'.body_html', $template->body_html) }}</textarea>
                                <p class="form-text small mb-0">{{ __('email_templates.placeholders_hint') }}</p>
                            </div>
                        </details>
                    @endforeach

                    <button type="submit" class="btn btn-primary btn-sm mt-2">{{ __('app.save') }}</button>
                </form>
            </div>
        </div>
    @endforeach
</div>

{{-- Preview modal --}}
<div class="modal fade" id="emailTemplatePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content app-card">
            <div class="modal-header">
                <h2 class="modal-title h5 mb-0">{{ __('email_templates.preview_title') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted-theme mb-1">{{ __('email_templates.preview_subject') }}</p>
                <p class="fw-semibold" id="email-preview-subject"></p>
                <hr>
                <iframe id="email-preview-frame" title="{{ __('email_templates.preview_title') }}"
                        sandbox="" class="w-100 border rounded" style="min-height:360px;background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js" referrerpolicy="origin"></script>
<script src="{{ asset('js/email-template-editor.js') }}?v=20260714c"></script>
@endpush
