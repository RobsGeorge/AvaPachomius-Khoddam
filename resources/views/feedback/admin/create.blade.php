@extends('layouts.app')

@section('title', __('pages.feedback_create_survey'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <a href="{{ route('feedback.index') }}" class="btn btn-outline-secondary btn-sm mb-3">{{ __('pages.back') }}</a>
    <h1 class="page-title mb-4">{{ __('pages.feedback_create_survey') }}</h1>

    <div class="app-card card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('feedback.surveys.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.course') }}</label>
                    <select name="course_id" id="course_id" class="form-select" required>
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}" @selected(old('course_id', $selectedCourse) == $course->course_id)>{{ $course->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.module') }}</label>
                    <select name="module_id" id="module_id" class="form-select" required>
                        <option value="">{{ __('pages.select_module') }}</option>
                        @foreach($courses as $course)
                            @foreach($course->modules as $module)
                                <option value="{{ $module->module_id }}" data-course="{{ $course->course_id }}"
                                    @selected(old('module_id', $selectedModule) == $module->module_id)>{{ $course->title }} — {{ $module->title }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.title') }}</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title') }}" required maxlength="200">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.description') }}</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.due_date') }}</label>
                    <input type="datetime-local" name="due_at" class="form-control" value="{{ old('due_at') }}">
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="is_mandatory" value="1" id="is_mandatory" @checked(old('is_mandatory', true))>
                    <label class="form-check-label" for="is_mandatory">{{ __('pages.feedback_mandatory_label') }}</label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('pages.continue_to_builder') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('course_id')?.addEventListener('change', function () {
    const courseId = this.value;
    document.querySelectorAll('#module_id option[data-course]').forEach(opt => {
        if (!opt.value) return;
        opt.hidden = opt.dataset.course !== courseId;
    });
    document.getElementById('module_id').value = '';
});
</script>
@endpush
