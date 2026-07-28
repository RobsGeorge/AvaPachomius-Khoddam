@extends('layouts.app')

@section('content')
<div class="container animate-in py-4" style="max-width:860px;">

    @php
        $course = $lecture->module->courses->first();
        $defaultMaterialSource = old('source_type', \App\Models\LectureMaterial::SOURCE_EXTERNAL_LINK);
    @endphp
    <div class="mb-3">
        @if($course)
            <a href="{{ route('curriculum.admin', $course->course_id) }}" class="text-muted small">
                <i class="bi bi-arrow-right"></i> {{ $course->title }}
            </a>
            <span class="text-muted small"> / {{ $lecture->module->title }}</span>
        @endif
    </div>

    <h1 class="mb-3">{{ __('pages.edit_lecture') }}</h1>

    @include('course-content.partials.storage-quota-bar')

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">{{ __('pages.lecture_details') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('lectures.update', $lecture->lecture_id) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.lecture_title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control"
                                   value="{{ old('title', $lecture->title) }}" maxlength="150" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.select_session') }} <span class="text-danger">*</span></label>
                            @if($lecture->module->courseSessions->isEmpty())
                                <div class="alert alert-warning small mb-0">
                                    {{ __('pages.no_sessions_in_module') }}
                                </div>
                            @else
                                <select name="session_id" class="form-select" required>
                                    <option value="">-- {{ __('pages.select_session') }} --</option>
                                    @foreach($lecture->module->courseSessions as $session)
                                        <option value="{{ $session->session_id }}"
                                            @selected(old('session_id', $lecture->session_id) == $session->session_id)>
                                            {{ __('pages.week') }} {{ $session->week_number ?? '?' }} —
                                            {{ $session->session_title }}
                                            ({{ $session->session_date?->format('Y-m-d') ?? __('pages.unspecified') }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">{{ __('pages.lecture_session_hint') }}</div>
                            @endif
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('pages.date') }}</label>
                                <input type="date" name="lecture_date" class="form-control"
                                       value="{{ old('lecture_date', $lecture->lecture_date?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ __('pages.sort_order') }}</label>
                                <input type="number" name="order_index" class="form-control"
                                       value="{{ old('order_index', $lecture->order_index) }}" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.video_url') }}</label>
                            <input type="url" name="video_link" class="form-control"
                                   value="{{ old('video_link', $lecture->video_link) }}" maxlength="500"
                                   placeholder="https://...">
                            <div class="form-text">{{ __('curriculum.slides_link_hint') }}</div>
                        </div>

                        @include('course-content.partials.slides-source-field', [
                            'lecture' => $lecture,
                            'maxUploadMb' => $maxUploadMb,
                            'allowedMimes' => $allowedMimes,
                        ])

                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.notes') }}</label>
                            <textarea name="notes" class="form-control" rows="4"
                                      placeholder="{{ __('pages.lecture_notes_placeholder') }}">{{ old('notes', $lecture->notes) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> {{ __('pages.save_changes') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-paperclip"></i> {{ __('pages.additional_materials') }}
                    <span class="badge bg-secondary ms-1">{{ $lecture->materials->count() }}</span>
                </div>

                <ul class="list-group list-group-flush">
                    @forelse($lecture->materials as $mat)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="min-w-0">
                                <div class="fw-semibold small d-flex align-items-center gap-1 flex-wrap">
                                    {{ $mat->title }}
                                    @if($mat->isHostedFile())
                                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ __('curriculum.hosted_file_badge') }}</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('curriculum.external_link_badge') }}</span>
                                    @endif
                                </div>
                                @if($url = $mat->accessUrl())
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                       class="text-primary small text-truncate d-block" style="max-width:220px;">
                                        {{ $mat->isHostedFile() ? ($mat->media->original_filename ?? $url) : $mat->link }}
                                    </a>
                                @endif
                            </div>
                            <form method="POST"
                                  action="{{ route('lecture-materials.destroy', $mat->material_id) }}"
                                  data-confirm="{{ __('pages.confirm_delete_link') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0 px-1" type="submit">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small text-center py-3">
                            {{ __('pages.no_additional_materials') }}.
                        </li>
                    @endforelse
                </ul>

                <div class="card-footer bg-light" data-curriculum-material-source>
                    <div class="small fw-semibold text-muted mb-2">{{ __('curriculum.add_material') }}</div>
                    <form method="POST" action="{{ route('lecture-materials.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="lecture_id" value="{{ $lecture->lecture_id }}">
                        <div class="mb-2">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.link_name_example') }}" maxlength="150" required>
                        </div>
                        <div class="btn-group btn-group-sm mb-2 w-100" role="group">
                            <input type="radio" class="btn-check" name="source_type" id="mat_source_link"
                                   value="{{ \App\Models\LectureMaterial::SOURCE_EXTERNAL_LINK }}"
                                   @checked($defaultMaterialSource === \App\Models\LectureMaterial::SOURCE_EXTERNAL_LINK)>
                            <label class="btn btn-outline-secondary" for="mat_source_link">{{ __('curriculum.source_external_link') }}</label>
                            <input type="radio" class="btn-check" name="source_type" id="mat_source_file"
                                   value="{{ \App\Models\LectureMaterial::SOURCE_HOSTED_FILE }}"
                                   @checked($defaultMaterialSource === \App\Models\LectureMaterial::SOURCE_HOSTED_FILE)>
                            <label class="btn btn-outline-secondary" for="mat_source_file">{{ __('curriculum.source_hosted_file') }}</label>
                        </div>
                        <div class="mb-2" data-material-panel="external_link" @class(['d-none' => $defaultMaterialSource !== \App\Models\LectureMaterial::SOURCE_EXTERNAL_LINK])>
                            <input type="url" name="link" class="form-control form-control-sm"
                                   placeholder="https://..." maxlength="500">
                        </div>
                        <div class="mb-2" data-material-panel="hosted_file" @class(['d-none' => $defaultMaterialSource !== \App\Models\LectureMaterial::SOURCE_HOSTED_FILE])>
                            <input type="file" name="file" class="form-control form-control-sm"
                                   accept="{{ '.' . implode(',.', $allowedMimes) }}">
                            <div class="form-text">{{ __('curriculum.upload_hint', ['max' => $maxUploadMb, 'types' => implode(', ', $allowedMimes)]) }}</div>
                        </div>
                        @error('file')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.add') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mt-3">
                <div class="card-body py-2">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">{{ __('pages.module') }}</dt>
                        <dd class="col-7">{{ $lecture->module->title }}</dd>
                        <dt class="col-5 text-muted">{{ __('pages.session') }}</dt>
                        <dd class="col-7">
                            @if($lecture->session)
                                {{ __('pages.week') }} {{ $lecture->session->week_number ?? '?' }} —
                                {{ $lecture->session->session_title }}
                            @else
                                <span class="text-warning">{{ __('pages.unassigned') }}</span>
                            @endif
                        </dd>
                        <dt class="col-5 text-muted">{{ __('pages.lecture_number') }}</dt>
                        <dd class="col-7">#{{ $lecture->lecture_id }}</dd>
                        <dt class="col-5 text-muted">{{ __('pages.created_at') }}</dt>
                        <dd class="col-7">{{ $lecture->created_at->format('Y-m-d') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-curriculum-material-source]');
    if (!root) return;
    const panels = root.querySelectorAll('[data-material-panel]');
    root.querySelectorAll('input[name="source_type"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            panels.forEach(function (panel) {
                panel.classList.toggle('d-none', panel.getAttribute('data-material-panel') !== radio.value);
            });
        });
    });
});
</script>
@endsection
