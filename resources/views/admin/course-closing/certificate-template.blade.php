@extends('layouts.app')

@section('title', __('course_graduation.certificate_template'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('course_graduation.certificate_template') }}</h1>
            <p class="text-muted-theme mb-0">{{ $course->title }} — {{ $course->year }}</p>
            <p class="small text-muted-theme mb-0">{{ __('course_graduation.certificate_template_hint') }}</p>
        </div>
        <a href="{{ route('courses.closing.show', $course->course_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

<p class="small text-muted-theme">{{ __('course_graduation.placeholders') }}</p>

    <form method="POST" action="{{ route('courses.certificate-template.update', $course->course_id) }}">
        @csrf
        @method('PUT')
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('pages.name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $template->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">HTML</label>
                    <textarea name="body_html" rows="12" class="form-control font-monospace" required>{{ old('body_html', $template->body_html) }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
