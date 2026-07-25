@extends('layouts.app')

@section('content')
<div class="container animate-in py-4" style="max-width:860px;">

    {{-- Back link --}}
    @php $course = $lecture->module->courses->first(); @endphp
    <div class="mb-3">
        @if($course)
            <a href="{{ route('curriculum.admin', $course->course_id) }}" class="text-muted small">
                <i class="bi bi-arrow-right"></i> {{ $course->title }}
            </a>
            <span class="text-muted small"> / {{ $lecture->module->title }}</span>
        @endif
    </div>

    <h1 class="mb-4">{{ __('pages.edit_lecture') }}</h1>

<div class="row g-4">

        {{-- Lecture details form --}}
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">{{ __('pages.lecture_details') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('lectures.update', $lecture->lecture_id) }}">
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
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.slides_url') }} / PDF</label>
                            <input type="url" name="slides_link" class="form-control"
                                   value="{{ old('slides_link', $lecture->slides_link) }}" maxlength="500"
                                   placeholder="https://...">
                        </div>
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

        {{-- Supplementary materials --}}
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-link-45deg"></i> {{ __('pages.additional_materials') }}
                    <span class="badge bg-secondary ms-1">{{ $lecture->materials->count() }}</span>
                </div>

                {{-- Existing materials --}}
                <ul class="list-group list-group-flush">
                    @forelse($lecture->materials as $mat)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small">{{ $mat->title }}</div>
                                <a href="{{ $mat->link }}" target="_blank"
                                   class="text-primary small text-truncate d-block" style="max-width:220px;">
                                    {{ $mat->link }}
                                </a>
                            </div>
                            <form method="POST"
                                  action="{{ route('lecture-materials.destroy', $mat->material_id) }}"
                                  data-confirm="{{ __('pages.confirm_delete_link') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger py-0 px-1">
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

                {{-- Add material form --}}
                <div class="card-footer bg-light">
                    <div class="small fw-semibold text-muted mb-2">{{ __('pages.add_new_link') }}</div>
                    <form method="POST" action="{{ route('lecture-materials.store') }}">
                        @csrf
                        <input type="hidden" name="lecture_id" value="{{ $lecture->lecture_id }}">
                        <div class="mb-2">
                            <input type="text" name="title" class="form-control form-control-sm"
                                   placeholder="{{ __('pages.link_name_example') }}" maxlength="150" required>
                        </div>
                        <div class="mb-2">
                            <input type="url" name="link" class="form-control form-control-sm"
                                   placeholder="https://..." maxlength="500" required>
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.add') }}
                        </button>
                    </form>
                </div>
            </div>

            {{-- Lecture info card --}}
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
@endsection
