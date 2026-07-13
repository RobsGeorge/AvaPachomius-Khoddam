@extends('layouts.app')

@section('title', __('pages.edit_session'))

@section('content')
<div class="container py-4 animate-in" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.edit_session') }}</h1>
        <a href="{{ route('sessions.index') }}" class="btn btn-outline-theme">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back') }}
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

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('sessions.update', $session->session_id) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.course') }} <span class="text-danger">*</span></label>
                    <select name="course_id" id="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}"
                                {{ old('course_id', $session->course_id) == $course->course_id ? 'selected' : '' }}>
                                {{ $course->title }} ({{ $course->year }})
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.module') }} / {{ __('pages.pillar') }} <span class="text-danger">*</span></label>
                    <select name="module_id" id="module_id" class="form-select @error('module_id') is-invalid @enderror" required>
                        <option value="">{{ __('pages.select_module') }}</option>
                        @foreach($courses as $course)
                            @foreach($course->modules as $module)
                                <option value="{{ $module->module_id }}"
                                        data-course-id="{{ $course->course_id }}"
                                        {{ (string) old('module_id', $session->module_id) === (string) $module->module_id ? 'selected' : '' }}>
                                    {{ $course->title }} — {{ $module->title }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('module_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.session_title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="session_title"
                           class="form-control @error('session_title') is-invalid @enderror"
                           value="{{ old('session_title', $session->session_title) }}"
                           maxlength="30" required>
                    @error('session_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pages.session_date') }} <span class="text-danger">*</span></label>
                    <input type="date" name="session_date"
                           class="form-control @error('session_date') is-invalid @enderror"
                           value="{{ old('session_date', $session->session_date?->format('Y-m-d')) }}" required>
                    @error('session_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pages.session_start_time') }}</label>
                    @php
                        $startTimeValue = old('session_start_time');
                        if ($startTimeValue === null && $session->session_start_time) {
                            $startTimeValue = is_string($session->session_start_time)
                                ? substr($session->session_start_time, 0, 5)
                                : $session->session_start_time->format('H:i');
                        }
                    @endphp
                    <input type="time" name="session_start_time"
                           class="form-control @error('session_start_time') is-invalid @enderror"
                           value="{{ $startTimeValue }}">
                    <div class="form-text text-muted-theme">{{ __('pages.session_start_time_hint') }}</div>
                    @error('session_start_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @include('sessions.partials.notification-settings', [
                    'session' => $session,
                    'rosterStudents' => $rosterStudents,
                ])

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ __('pages.save_changes') }}
                    </button>
                    <a href="{{ route('sessions.index') }}" class="btn btn-outline-theme">{{ __('pages.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm mt-4">
        <div class="card-header fw-semibold text-muted-theme">{{ __('pages.session_info') }}</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ __('pages.session_id') }}</dt>
                <dd class="col-sm-8">#{{ $session->session_id }}</dd>
                <dt class="col-sm-4">{{ __('pages.attendance_records_count') }}</dt>
                <dd class="col-sm-8">{{ $session->attendances->count() }}</dd>
                <dt class="col-sm-4">{{ __('pages.created_at') }}</dt>
                <dd class="col-sm-8">{{ $session->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function filterModulesByCourse() {
    const courseId = document.getElementById('course_id')?.value;
    const moduleSelect = document.getElementById('module_id');
    if (!moduleSelect) return;

    let hasVisible = false;
    Array.from(moduleSelect.options).forEach((opt, idx) => {
        if (idx === 0) return;
        const match = !courseId || opt.dataset.courseId === courseId;
        opt.hidden = !match;
        if (match && opt.value === moduleSelect.value) hasVisible = true;
    });
    if (!hasVisible) moduleSelect.value = '';
}
document.getElementById('course_id')?.addEventListener('change', filterModulesByCourse);
document.addEventListener('DOMContentLoaded', filterModulesByCourse);
</script>
@endpush
