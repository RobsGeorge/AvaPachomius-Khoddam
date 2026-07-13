@extends('layouts.app')

@section('title', __('pages.edit_module'))

@section('content')
<div class="container py-4 animate-in" style="max-width:600px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.edit_module') }}</h1>
        <a href="{{ route('modules.index') }}" class="btn btn-outline-theme btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back') }}
        </a>
    </div>

<div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('modules.update', $module->module_id) }}">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.module_name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $module->title) }}" maxlength="30" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pages.description') }} <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                              rows="3" maxlength="255" required>{{ old('description', $module->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pages.link_another_course') }}</label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror">
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            @if(! in_array($course->course_id, $linkedCourseIds, true))
                                <option value="{{ $course->course_id }}" @selected(old('course_id') == $course->course_id)>
                                    {{ $course->title }} ({{ $course->year }})
                                </option>
                            @endif
                        @endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> {{ __('pages.save_changes') }}
                </button>
            </form>
        </div>
    </div>

    @if($module->courses->isNotEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-header fw-semibold text-muted-theme small">{{ __('pages.linked_courses') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($module->courses as $course)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $course->title }} <span class="text-muted-theme">({{ $course->year }})</span></span>
                        <a href="{{ route('curriculum.admin', $course->course_id) }}"
                           class="btn btn-sm btn-outline-theme py-0">
                            <i class="bi bi-gear"></i> {{ __('pages.manage_curriculum') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
