@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">إنشاء محاضرات</h1>
        <a href="{{ route('sessions.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('sessions.store') }}" id="sessionForm">
                @csrf

                {{-- Course --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">الدورة <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">-- اختر الدورة --</option>
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

                {{-- Title --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        عنوان الجلسة <span class="text-danger">*</span>
                        <small class="text-muted fw-normal">(للجلسات المتعددة سيُضاف رقم تلقائياً: "محاضرة 1"، "محاضرة 2"...)</small>
                    </label>
                    <input type="text" name="session_title"
                           class="form-control @error('session_title') is-invalid @enderror"
                           value="{{ old('session_title', 'محاضرة') }}" maxlength="27" required>
                    @error('session_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Creation Mode --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">طريقة الإنشاء <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="creation_mode"
                                   id="mode_single" value="single"
                                   {{ old('creation_mode', 'single') === 'single' ? 'checked' : '' }}
                                   onchange="switchMode('single')">
                            <label class="form-check-label" for="mode_single">
                                <i class="bi bi-calendar-event"></i> تاريخ واحد
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="creation_mode"
                                   id="mode_multi" value="multi"
                                   {{ old('creation_mode') === 'multi' ? 'checked' : '' }}
                                   onchange="switchMode('multi')">
                            <label class="form-check-label" for="mode_multi">
                                <i class="bi bi-calendar3"></i> تواريخ محددة متعددة
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="creation_mode"
                                   id="mode_weekly" value="weekly"
                                   {{ old('creation_mode') === 'weekly' ? 'checked' : '' }}
                                   onchange="switchMode('weekly')">
                            <label class="form-check-label" for="mode_weekly">
                                <i class="bi bi-calendar-week"></i> أسبوعي تلقائي
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Panel: Single Date --}}
                <div id="panel_single" class="mode-panel border rounded p-3 mb-3">
                    <label class="form-label fw-semibold">تاريخ الجلسة</label>
                    <input type="date" name="single_date"
                           class="form-control @error('single_date') is-invalid @enderror"
                           value="{{ old('single_date') }}">
                    @error('single_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Panel: Multiple Dates --}}
                <div id="panel_multi" class="mode-panel border rounded p-3 mb-3" style="display:none;">
                    <label class="form-label fw-semibold d-block mb-2">التواريخ</label>
                    <div id="multi_dates_list">
                        @if(old('dates'))
                            @foreach(old('dates') as $d)
                                <div class="d-flex gap-2 mb-2 date-row">
                                    <input type="date" name="dates[]" class="form-control" value="{{ $d }}">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeDate(this)">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            @endforeach
                        @else
                            <div class="d-flex gap-2 mb-2 date-row">
                                <input type="date" name="dates[]" class="form-control">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeDate(this)" disabled>
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        @endif
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-1" onclick="addDate()">
                        <i class="bi bi-plus-circle"></i> إضافة تاريخ
                    </button>
                    @error('dates')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Panel: Weekly --}}
                <div id="panel_weekly" class="mode-panel border rounded p-3 mb-3" style="display:none;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">تاريخ أول محاضرة</label>
                            <input type="date" name="start_date" id="start_date"
                                   class="form-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date') }}" onchange="updatePreview()">
                            @error('start_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">عدد الأسابيع</label>
                            <input type="number" name="weeks" id="weeks_input"
                                   class="form-control @error('weeks') is-invalid @enderror"
                                   value="{{ old('weeks', 12) }}" min="1" max="52" onchange="updatePreview()">
                            @error('weeks')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div id="weekly_preview" class="mt-3 p-2 bg-light rounded small text-muted" style="display:none;">
                        <strong>معاينة:</strong>
                        <div id="preview_list" class="mt-1"></div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> إنشاء الجلسات
                    </button>
                    <a href="{{ route('sessions.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const dayNames = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];

function switchMode(mode) {
    document.querySelectorAll('.mode-panel').forEach(p => p.style.display = 'none');
    document.getElementById('panel_' + mode).style.display = 'block';
}

function addDate() {
    const list = document.getElementById('multi_dates_list');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 mb-2 date-row';
    row.innerHTML = `<input type="date" name="dates[]" class="form-control">
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
    rows.forEach((row, i) => {
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

// Initialize correct panel on page load
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="creation_mode"]:checked');
    if (checked) switchMode(checked.value);
    updatePreview();
});
</script>
@endpush
