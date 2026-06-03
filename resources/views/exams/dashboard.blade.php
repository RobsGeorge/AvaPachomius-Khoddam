@extends('layouts.app')

@section('title', __('pages.exams_management'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.exams_management') }}</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
            <i class="bi bi-plus-circle"></i> {{ __('pages.add_new_exam') }}
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pages.exam_name') }}</th>
                            <th>{{ __('pages.course') }}</th>
                            <th>{{ __('pages.module') }}</th>
                            <th>{{ __('pages.duration') }}</th>
                            <th>{{ __('pages.exam_schedules') }}</th>
                            <th>{{ __('pages.study_resources') }}</th>
                            <th>{{ __('pages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($exams as $exam)
                            <tr>
                                <td class="fw-semibold">{{ $exam->exam_name }}</td>
                                <td>{{ $exam->course->title ?? '—' }}</td>
                                <td>{{ $exam->module->title ?? '—' }}</td>
                                <td>{{ $exam->duration_minutes }} {{ __('pages.minutes') }}</td>
                                <td>
                                    @foreach($exam->schedules as $schedule)
                                        <div class="small mb-1">
                                            {{ $schedule->scheduled_date->format('Y-m-d H:i') }}
                                            @if($schedule->is_completed)
                                                <span class="badge bg-success">{{ __('pages.done') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('pages.not_done') }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 js-open-schedule-modal"
                                            data-exam-id="{{ $exam->exam_id }}"
                                            data-exam-name="{{ $exam->exam_name }}"
                                            data-schedule-url="{{ route('exams.schedule', $exam->exam_id) }}">
                                        + {{ __('pages.add_schedule') }}
                                    </button>
                                </td>
                                <td class="small text-muted-theme">{{ Str::limit($exam->study_resources, 60) }}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-theme js-open-edit-modal"
                                                data-exam-id="{{ $exam->exam_id }}"
                                                data-exam-name="{{ e($exam->exam_name) }}"
                                                data-duration="{{ $exam->duration_minutes }}"
                                                data-study-resources="{{ e($exam->study_resources ?? '') }}"
                                                data-course-id="{{ $exam->course_id }}"
                                                data-module-id="{{ $exam->module_id }}"
                                                data-update-url="{{ route('exams.update', $exam->exam_id) }}">
                                            {{ __('pages.edit') }}
                                        </button>
                                        <form method="POST" action="{{ route('exams.destroy', $exam->exam_id) }}"
                                              onsubmit="return confirm(@json(__('pages.confirm_delete_exam_js')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('pages.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted-theme py-4">{{ __('pages.no_exams_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
{{-- Add exam --}}
<div class="modal fade" id="addExamModal" tabindex="-1" aria-labelledby="addExamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
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
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_duration_minutes') }} *</label>
                        <input type="number" name="duration_minutes" class="form-control" min="1" value="{{ old('duration_minutes', 60) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.study_resources') }}</label>
                        <textarea name="study_resources" class="form-control" rows="2">{{ old('study_resources') }}</textarea>
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
    <div class="modal-dialog">
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
    <div class="modal-dialog">
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
                                <option value="{{ $module->module_id }}">{{ $module->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_name') }} *</label>
                        <input type="text" name="exam_name" id="editExamName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.exam_duration_minutes') }} *</label>
                        <input type="number" name="duration_minutes" id="editExamDuration" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('pages.study_resources') }}</label>
                        <textarea name="study_resources" id="editExamResources" class="form-control" rows="2"></textarea>
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
    /** Remove stray Bootstrap backdrops that block the whole page. */
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
            document.getElementById('editExamDuration').value = btn.dataset.duration;
            document.getElementById('editExamResources').value = btn.dataset.studyResources || '';
            document.getElementById('editExamCourse').value = btn.dataset.courseId || '';
            document.getElementById('editExamModule').value = btn.dataset.moduleId || '';
            bootstrap.Modal.getOrCreateInstance(editModalEl).show();
        });
    });

    [scheduleModalEl, editModalEl, document.getElementById('addExamModal')].forEach(function (el) {
        if (!el) return;
        el.addEventListener('hidden.bs.modal', clearStuckModalState);
    });

    const courseSelect = document.getElementById('addExamCourse');
    const moduleSelect = document.getElementById('addExamModule');
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
