@extends('layouts.app')

@section('title', __('pages.create_sessions'))

@section('content')
<div class="container py-4 animate-in" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.create_sessions') }}</h1>
        <a href="{{ route('sessions.index') }}" class="btn btn-outline-theme">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back') }}
        </a>
    </div>

    @if($errors->has('general'))
        <div class="alert alert-danger">{{ $errors->first('general') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('sessions.store') }}" id="sessionForm">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.course') }} <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}"
                                {{ old('course_id') == $course->course_id ? 'selected' : '' }}>
                                {{ $course->title }} ({{ $course->year }})
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        {{ __('pages.session_title') }} <span class="text-danger">*</span>
                        <small class="text-muted-theme fw-normal">{{ __('pages.session_title_hint') }}</small>
                    </label>
                    <input type="text" name="session_title"
                           class="form-control @error('session_title') is-invalid @enderror"
                           value="{{ old('session_title', __('pages.default_lecture_title')) }}" maxlength="27" required>
                    @error('session_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pages.creation_mode') }} <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="creation_mode"
                                   id="mode_single" value="single"
                                   {{ old('creation_mode', 'single') === 'single' ? 'checked' : '' }}
                                   onchange="switchMode('single')">
                            <label class="form-check-label" for="mode_single">
                                <i class="bi bi-calendar-event"></i> {{ __('pages.mode_single') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="creation_mode"
                                   id="mode_multi" value="multi"
                                   {{ old('creation_mode') === 'multi' ? 'checked' : '' }}
                                   onchange="switchMode('multi')">
                            <label class="form-check-label" for="mode_multi">
                                <i class="bi bi-calendar3"></i> {{ __('pages.mode_multi') }}
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="creation_mode"
                                   id="mode_weekly" value="weekly"
                                   {{ old('creation_mode') === 'weekly' ? 'checked' : '' }}
                                   onchange="switchMode('weekly')">
                            <label class="form-check-label" for="mode_weekly">
                                <i class="bi bi-calendar-week"></i> {{ __('pages.mode_weekly') }}
                            </label>
                        </div>
                    </div>
                </div>

                <div id="panel_single" class="mode-panel border rounded p-3 mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.session_date') }}</label>
                    @php
                        $singleDateValue = old('single_date', '');
                        if ($singleDateValue && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $singleDateValue)) {
                            try {
                                $singleDateValue = \Carbon\Carbon::createFromFormat('d/m/Y', $singleDateValue)->format('Y-m-d');
                            } catch (\Throwable) {
                                $singleDateValue = '';
                            }
                        }
                    @endphp
                    <input type="date" name="single_date" data-session-mode="single"
                           class="form-control @error('single_date') is-invalid @enderror"
                           value="{{ $singleDateValue }}">
                    @error('single_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div id="panel_multi" class="mode-panel border rounded p-3 mb-3" style="display:none;">
                    <label class="form-label fw-semibold d-block mb-2">{{ __('pages.dates') }}</label>
                    <div id="multi_dates_list">
                        @if(old('dates'))
                            @foreach(old('dates') as $d)
                                <div class="d-flex gap-2 mb-2 date-row">
                                    <input type="date" name="dates[]" data-session-mode="multi" class="form-control" value="{{ $d }}">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeDate(this)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            @endforeach
                        @else
                            <div class="d-flex gap-2 mb-2 date-row">
                                <input type="date" name="dates[]" data-session-mode="multi" class="form-control">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeDate(this)" disabled>
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-outline-theme btn-sm mt-1" onclick="addDate()">
                        <i class="bi bi-plus-circle"></i> {{ __('pages.add_date') }}
                    </button>
                    @error('dates')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    @error('dates.*')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div id="panel_weekly" class="mode-panel border rounded p-3 mb-3" style="display:none;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('pages.first_lecture_date') }}</label>
                            <input type="date" name="start_date" id="start_date" data-session-mode="weekly"
                                   class="form-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date') }}" onchange="updatePreview()">
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ __('pages.weeks_count') }}</label>
                            <input type="number" name="weeks" id="weeks_input" data-session-mode="weekly"
                                   class="form-control @error('weeks') is-invalid @enderror"
                                   value="{{ old('weeks', 12) }}" min="1" max="52" onchange="updatePreview()">
                            @error('weeks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div id="weekly_preview" class="mt-3 p-2 bg-light rounded small text-muted-theme" style="display:none;">
                        <strong>{{ __('pages.preview') }}</strong>
                        <div id="preview_list" class="mt-1"></div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ __('pages.create_sessions_btn') }}
                    </button>
                    <a href="{{ route('sessions.index') }}" class="btn btn-outline-theme">{{ __('pages.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@php
    $weekDayNames = [
        __('pages.day_sun'),
        __('pages.day_mon'),
        __('pages.day_tue'),
        __('pages.day_wed'),
        __('pages.day_thu'),
        __('pages.day_fri'),
        __('pages.day_sat'),
    ];
@endphp
<script>
const dayNames = @json($weekDayNames);

function switchMode(mode) {
    document.querySelectorAll('.mode-panel').forEach(p => p.style.display = 'none');
    document.getElementById('panel_' + mode).style.display = 'block';
}

function addDate() {
    const list = document.getElementById('multi_dates_list');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 mb-2 date-row';
    row.innerHTML = `<input type="date" name="dates[]" data-session-mode="multi" class="form-control">
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeDate(this)">
            <i class="bi bi-x-lg"></i>
        </button>`;
    list.appendChild(row);
    updateRemoveButtons();
}

function removeDate(btn) {
    btn.closest('.date-row').remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const rows = document.querySelectorAll('.date-row');
    rows.forEach((row) => {
        row.querySelector('button').disabled = (rows.length === 1);
    });
}

function updatePreview() {
    const startVal = document.getElementById('start_date').value;
    const weeks    = parseInt(document.getElementById('weeks_input').value) || 0;
    const preview  = document.getElementById('weekly_preview');
    const list     = document.getElementById('preview_list');

    if (!startVal || weeks < 1) { preview.style.display = 'none'; return; }

    const start = new Date(startVal + 'T00:00:00');
    const items = [];
    for (let i = 0; i < weeks; i++) {
        const d = new Date(start);
        d.setDate(d.getDate() + i * 7);
        const dateStr = d.toISOString().slice(0, 10);
        const dayName = dayNames[d.getDay()];
        items.push(`<span class="badge bg-secondary me-1 mb-1">${i+1}: ${dateStr} (${dayName})</span>`);
    }
    list.innerHTML = items.join('');
    preview.style.display = 'block';
}

document.getElementById('sessionForm').addEventListener('submit', () => {
    const mode = document.querySelector('input[name="creation_mode"]:checked')?.value || 'single';
    document.querySelectorAll('[data-session-mode]').forEach((el) => {
        if (el.dataset.sessionMode !== mode) {
            el.removeAttribute('name');
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="creation_mode"]:checked');
    if (checked) switchMode(checked.value);
    updatePreview();
});
</script>
@endpush
