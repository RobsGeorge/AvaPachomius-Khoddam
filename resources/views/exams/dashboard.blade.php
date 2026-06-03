@extends('layouts.app')

@section('title', __('pages.exams_management'))

@section('content')
<div class="container py-4 animate-in">
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
                                    <button type="button" class="btn btn-link btn-sm p-0"
                                            data-bs-toggle="modal" data-bs-target="#scheduleModal-{{ $exam->exam_id }}">
                                        + {{ __('pages.add_schedule') }}
                                    </button>
                                </td>
                                <td class="small text-muted-theme">{{ Str::limit($exam->study_resources, 60) }}</td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-theme"
                                                data-bs-toggle="modal" data-bs-target="#editExamModal-{{ $exam->exam_id }}">
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

{{-- Add exam --}}
<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('exams.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('pages.add_new_exam') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

@foreach($exams as $exam)
    <div class="modal fade" id="scheduleModal-{{ $exam->exam_id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('exams.schedule', $exam->exam_id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('pages.add_schedule') }} — {{ $exam->exam_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
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

    <div class="modal fade" id="editExamModal-{{ $exam->exam_id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('exams.update', $exam->exam_id) }}">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('pages.edit') }} — {{ $exam->exam_name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.course') }} *</label>
                            <select name="course_id" class="form-select edit-exam-course" required>
                                @foreach($courses as $course)
                                    <option value="{{ $course->course_id }}" @selected($exam->course_id == $course->course_id)>
                                        {{ $course->title }} ({{ $course->year }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.pillar') }} *</label>
                            <select name="module_id" class="form-select" required>
                                @foreach($modules as $module)
                                    <option value="{{ $module->module_id }}" @selected($exam->module_id == $module->module_id)>
                                        {{ $module->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.exam_name') }} *</label>
                            <input type="text" name="exam_name" class="form-control" value="{{ $exam->exam_name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.exam_duration_minutes') }} *</label>
                            <input type="number" name="duration_minutes" class="form-control" min="1" value="{{ $exam->duration_minutes }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('pages.study_resources') }}</label>
                            <textarea name="study_resources" class="form-control" rows="2">{{ $exam->study_resources }}</textarea>
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
@endforeach

@push('scripts')
<script>
(function () {
    const courseSelect = document.getElementById('addExamCourse');
    const moduleSelect = document.getElementById('addExamModule');
    if (!courseSelect || !moduleSelect) return;

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
})();
</script>
@endpush
@endsection
