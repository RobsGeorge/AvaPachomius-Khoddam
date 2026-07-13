@extends('layouts.app')

@section('title', __('pages.exams_management'))

@section('content')
<div class="container py-4 exams-hub">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('pages.exams_management') }}</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
            <i class="bi bi-plus-circle"></i> {{ __('pages.add_new_exam') }}
        </button>
    </div>

<div class="row g-3">
        @forelse($exams as $exam)
            <div class="col-12">
                <div class="app-card card shadow-sm exam-admin-card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">{{ $exam->exam_name }}</h5>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge {{ ($exam->delivery_mode ?? 'offline') === 'online' ? 'bg-primary' : 'bg-secondary' }}">
                                        {{ ($exam->delivery_mode ?? 'offline') === 'online' ? __('exams.mode_online') : __('exams.mode_offline') }}
                                    </span>
                                    @if($exam->is_published ?? false)
                                        <span class="badge bg-success">{{ __('exams.published') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <dl class="exam-meta-list mb-3">
                            <div class="exam-meta-row">
                                <dt>{{ __('pages.course') }}</dt>
                                <dd>{{ $exam->course->title ?? '—' }}</dd>
                            </div>
                            <div class="exam-meta-row">
                                <dt>{{ __('pages.module') }}</dt>
                                <dd>{{ $exam->module->title ?? '—' }}</dd>
                            </div>
                            <div class="exam-meta-row">
                                <dt>{{ __('pages.duration') }}</dt>
                                <dd>{{ $exam->duration_minutes }} {{ __('pages.minutes') }}</dd>
                            </div>
                            <div class="exam-meta-row">
                                <dt>{{ __('pages.exam_schedules') }}</dt>
                                <dd>
                                    @forelse($exam->schedules as $schedule)
                                        <div class="mb-1">
                                            {{ $schedule->scheduled_date->format('Y-m-d H:i') }}
                                            @if($schedule->is_completed)
                                                <span class="badge bg-success">{{ __('pages.done') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('pages.not_done') }}</span>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-muted">—</span>
                                    @endforelse
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 js-open-schedule-modal"
                                            data-exam-id="{{ $exam->exam_id }}"
                                            data-exam-name="{{ $exam->exam_name }}"
                                            data-schedule-url="{{ route('exams.schedule', $exam->exam_id) }}">
                                        + {{ __('pages.add_schedule') }}
                                    </button>
                                </dd>
                            </div>
                            @if($exam->study_resources)
                                <div class="exam-meta-row">
                                    <dt>{{ __('pages.study_resources') }}</dt>
                                    <dd class="small">{{ $exam->study_resources }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="d-flex flex-wrap gap-2 border-top pt-3">
                            <a href="{{ route('exams.builder', $exam) }}" class="btn btn-sm btn-outline-primary flex-grow-1 flex-sm-grow-0">
                                <i class="bi bi-ui-checks"></i> {{ __('exams.design_exam') }}
                            </a>
                            <a href="{{ route('exams.grades', $exam) }}" class="btn btn-sm btn-outline-success flex-grow-1 flex-sm-grow-0">
                                <i class="bi bi-bar-chart"></i> {{ __('exams.grades_dashboard') }}
                            </a>
                            <button type="button"
                                    class="btn btn-sm btn-outline-theme flex-grow-1 flex-sm-grow-0 js-open-edit-modal"
                                    data-exam-id="{{ $exam->exam_id }}"
                                    data-exam-name="{{ e($exam->exam_name) }}"
                                    data-exam-type="{{ $exam->exam_type ?? 'exam' }}"
                                    data-delivery-mode="{{ $exam->delivery_mode ?? 'offline' }}"
                                    data-duration="{{ $exam->duration_minutes }}"
                                    data-shuffle="{{ ($exam->shuffle_questions ?? false) ? '1' : '0' }}"
                                    data-late-entry="{{ ($exam->allow_late_entry ?? true) ? '1' : '0' }}"
                                    data-study-resources="{{ e($exam->study_resources ?? '') }}"
                                    data-exam-description="{{ e($exam->exam_description ?? '') }}"
                                    data-passing-score="{{ $exam->passing_score ?? '' }}"
                                    data-course-id="{{ $exam->course_id }}"
                                    data-module-id="{{ $exam->module_id }}"
                                    data-update-url="{{ route('exams.update', $exam->exam_id) }}">
                                {{ __('pages.edit') }}
                            </button>
                            <form method="POST" action="{{ route('exams.destroy', $exam->exam_id) }}" class="flex-grow-1 flex-sm-grow-0"
                                  data-confirm="{{ __('pages.confirm_delete_exam_js') }}"
                                  onsubmit="return confirm(this.dataset.confirm)">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">{{ __('pages.delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="app-tile text-center text-muted-theme py-5">{{ __('pages.no_exams_yet') }}</div>
            </div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
<style>
.exams-hub .exam-meta-list { display: flex; flex-direction: column; gap: 0.65rem; }
.exams-hub .exam-meta-row {
    display: grid;
    grid-template-columns: minmax(0, 34%) minmax(0, 1fr);
    gap: 0.5rem 0.75rem;
    align-items: start;
}
.exams-hub .exam-meta-row dt {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
    word-break: break-word;
}
.exams-hub .exam-meta-row dd {
    margin: 0;
    font-size: 0.95rem;
    word-break: break-word;
}
.exams-hub .exam-admin-card .btn { white-space: normal; }
</style>
@endpush

@push('modals')
{{-- Add exam --}}
<div class="modal fade" id="addExamModal" tabindex="-1" aria-labelledby="addExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="{{ route('exams.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addExamModalLabel">{{ __('pages.add_new_exam') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.course') }} *</label>
                        <select name="course_id" id="addExamCourse" class="form-select" required>
                            <option value="">{{ __('pages.select_course') }}</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->course_id }}" @selected(old('course_id') == $course->course_id)>
                                    {{ $course->title }} ({{ $course->year }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.pillar') }} *</label>
                        <select name="module_id" id="addExamModule" class="form-select" required>
                            <option value="">{{ __('pages.select_module') }}</option>
                            @foreach($modules as $module)
                                <option value="{{ $module->module_id }}"
                                        data-courses="{{ $module->courses->pluck('course_id')->join(',') }}"
                                        @selected(old('module_id') == $module->module_id)>
                                    {{ $module->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_name') }} *</label>
                        <input type="text" name="exam_name" class="form-control" value="{{ old('exam_name') }}" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('exams.exam_type') }}</label>
                            <select name="exam_type" class="form-select">
                                <option value="exam">{{ __('exams.type_exam') }}</option>
                                <option value="quiz">{{ __('exams.type_quiz') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('exams.delivery_mode') }}</label>
                            <select name="delivery_mode" class="form-select">
                                <option value="offline">{{ __('exams.mode_offline') }}</option>
                                <option value="online">{{ __('exams.mode_online') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_duration_minutes') }} *</label>
                        <input type="number" name="duration_minutes" class="form-control" min="1" value="{{ old('duration_minutes', 60) }}" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="shuffle_questions" value="1" class="form-check-input" id="addShuffle">
                        <label class="form-check-label" for="addShuffle">{{ __('exams.shuffle_questions') }}</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="allow_late_entry" value="1" class="form-check-input" id="addLateEntry" checked>
                        <label class="form-check-label" for="addLateEntry">{{ __('exams.allow_late_entry') }}</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.study_resources') }}</label>
                        <textarea name="study_resources" class="form-control" rows="2">{{ old('study_resources') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.instructions') }}</label>
                        <textarea name="exam_description" class="form-control" rows="2">{{ old('exam_description') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.passing_score') }} (%)</label>
                        <input type="number" name="passing_score" class="form-control" min="0" max="100" value="{{ old('passing_score') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-theme" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Shared schedule modal --}}
<div class="modal fade" id="scheduleExamModal" tabindex="-1" aria-labelledby="scheduleExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" id="scheduleExamForm" action="#">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="scheduleExamModalLabel">{{ __('pages.add_schedule') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted-theme small mb-3" id="scheduleExamExamName"></p>
                    <label class="form-label">{{ __('pages.exam_schedule_datetime') }} *</label>
                    <input type="datetime-local" name="scheduled_date" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-theme" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Shared edit modal --}}
<div class="modal fade" id="editExamModal" tabindex="-1" aria-labelledby="editExamModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" id="editExamForm" action="#">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editExamModalLabel">{{ __('pages.edit') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('pages.close') }}"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.course') }} *</label>
                        <select name="course_id" id="editExamCourse" class="form-select" required>
                            @foreach($courses as $course)
                                <option value="{{ $course->course_id }}">
                                    {{ $course->title }} ({{ $course->year }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.pillar') }} *</label>
                        <select name="module_id" id="editExamModule" class="form-select" required>
                            @foreach($modules as $module)
                                <option value="{{ $module->module_id }}"
                                        data-courses="{{ $module->courses->pluck('course_id')->join(',') }}">
                                    {{ $module->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_name') }} *</label>
                        <input type="text" name="exam_name" id="editExamName" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ __('exams.exam_type') }}</label>
                            <select name="exam_type" id="editExamType" class="form-select">
                                <option value="exam">{{ __('exams.type_exam') }}</option>
                                <option value="quiz">{{ __('exams.type_quiz') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ __('exams.delivery_mode') }}</label>
                            <select name="delivery_mode" id="editExamDeliveryMode" class="form-select">
                                <option value="offline">{{ __('exams.mode_offline') }}</option>
                                <option value="online">{{ __('exams.mode_online') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_duration_minutes') }} *</label>
                        <input type="number" name="duration_minutes" id="editExamDuration" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="shuffle_questions" value="1" class="form-check-input" id="editExamShuffle">
                        <label class="form-check-label" for="editExamShuffle">{{ __('exams.shuffle_questions') }}</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="allow_late_entry" value="1" class="form-check-input" id="editExamLateEntry">
                        <label class="form-check-label" for="editExamLateEntry">{{ __('exams.allow_late_entry') }}</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.study_resources') }}</label>
                        <textarea name="study_resources" id="editExamResources" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.instructions') }}</label>
                        <textarea name="exam_description" id="editExamDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('exams.passing_score') }} (%)</label>
                        <input type="number" name="passing_score" id="editExamPassingScore" class="form-control" min="0" max="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-theme" data-bs-dismiss="modal">{{ __('pages.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
(function () {
    function clearStuckModalState() {
        document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    clearStuckModalState();

    const scheduleModalEl = document.getElementById('scheduleExamModal');
    const editModalEl = document.getElementById('editExamModal');

    document.querySelectorAll('.js-open-schedule-modal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = document.getElementById('scheduleExamForm');
            form.action = btn.dataset.scheduleUrl;
            document.getElementById('scheduleExamExamName').textContent = btn.dataset.examName;
            form.querySelector('[name="scheduled_date"]').value = '';
            bootstrap.Modal.getOrCreateInstance(scheduleModalEl).show();
        });
    });

    document.querySelectorAll('.js-open-edit-modal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = document.getElementById('editExamForm');
            form.action = btn.dataset.updateUrl;
            document.getElementById('editExamModalLabel').textContent = @json(__('pages.edit')) + ' — ' + btn.dataset.examName;
            document.getElementById('editExamName').value = btn.dataset.examName;
            document.getElementById('editExamType').value = btn.dataset.examType || 'exam';
            document.getElementById('editExamDeliveryMode').value = btn.dataset.deliveryMode || 'offline';
            document.getElementById('editExamDuration').value = btn.dataset.duration;
            document.getElementById('editExamShuffle').checked = btn.dataset.shuffle === '1';
            document.getElementById('editExamLateEntry').checked = btn.dataset.lateEntry !== '0';
            document.getElementById('editExamResources').value = btn.dataset.studyResources || '';
            document.getElementById('editExamDescription').value = btn.dataset.examDescription || '';
            document.getElementById('editExamPassingScore').value = btn.dataset.passingScore || '';
            document.getElementById('editExamCourse').value = btn.dataset.courseId || '';
            filterEditModules(btn.dataset.moduleId || '');
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        });
    });

    [scheduleModalEl, editModalEl, document.getElementById('addExamModal')].forEach(function (el) {
        if (!el) return;
        el.addEventListener('hidden.bs.modal', clearStuckModalState);
    });

    const courseSelect = document.getElementById('addExamCourse');
    const moduleSelect = document.getElementById('addExamModule');
    const editCourseSelect = document.getElementById('editExamCourse');
    const editModuleSelect = document.getElementById('editExamModule');
    let editModuleOptions = [];

    if (editModuleSelect) {
        editModuleOptions = Array.from(editModuleSelect.querySelectorAll('option[data-courses]'));
    }

    function filterEditModules(preferredModuleId) {
        if (!editCourseSelect || !editModuleSelect) return;
        const courseId = editCourseSelect.value;
        const current = preferredModuleId || editModuleSelect.value;
        editModuleSelect.innerHTML = '';
        editModuleOptions.forEach(function (opt) {
            const courses = (opt.dataset.courses || '').split(',').filter(Boolean);
            if (!courseId || courses.includes(courseId)) {
                editModuleSelect.appendChild(opt.cloneNode(true));
            }
        });
        if (current) editModuleSelect.value = current;
    }

    if (editCourseSelect && editModuleSelect) {
        editCourseSelect.addEventListener('change', function () {
            filterEditModules('');
        });
    }

    if (courseSelect && moduleSelect) {
        const allOptions = Array.from(moduleSelect.querySelectorAll('option[data-courses]'));

        function filterModules() {
            const courseId = courseSelect.value;
            const current = moduleSelect.value;
            moduleSelect.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = @json(__('pages.select_module'));
            moduleSelect.appendChild(placeholder);
            allOptions.forEach(function (opt) {
                const courses = (opt.dataset.courses || '').split(',').filter(Boolean);
                if (!courseId || courses.includes(courseId)) {
                    moduleSelect.appendChild(opt.cloneNode(true));
                }
            });
            if (current) moduleSelect.value = current;
        }

        courseSelect.addEventListener('change', filterModules);
        filterModules();
    }

    @if($errors->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addExamModal')).show();
    @endif
})();
</script>
@endpush
