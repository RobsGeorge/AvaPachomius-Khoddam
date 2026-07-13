@extends('layouts.app')

@section('title', __('pages.create_new_module'))

@section('content')
<div class="container py-4 animate-in" style="max-width:600px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.create_new_module') }}</h1>
        <a href="{{ route('modules.index') }}" class="btn btn-outline-theme btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back') }}
        </a>
    </div>

<div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('modules.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.module_name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}" maxlength="30" required>
                    <div class="form-text text-muted-theme">{{ __('pages.max_30_chars') }}</div>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pages.description') }} <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                              rows="3" maxlength="255" required>{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pages.link_course_optional') }}</label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror">
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}" @selected(old('course_id', $defaultCourseId ?? null) == $course->course_id)>
                                {{ $course->title }} ({{ $course->year }})
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> {{ __('pages.create') }}
                    </button>
                    <a href="{{ route('modules.index') }}" class="btn btn-outline-theme">{{ __('pages.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
