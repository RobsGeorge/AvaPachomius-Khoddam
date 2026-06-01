@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:600px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">تعديل الوحدة</h1>
        <a href="{{ route('modules.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
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

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('modules.update', $module->module_id) }}">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-semibold">اسم الوحدة <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $module->title) }}" maxlength="30" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">الوصف <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                              rows="3" maxlength="255" required>{{ old('description', $module->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> حفظ التعديلات
                </button>
            </form>
        </div>
    </div>

    @if($module->courses->isNotEmpty())
        <div class="card shadow-sm">
            <div class="card-header fw-semibold text-muted small">الدورات المرتبطة بهذه الوحدة</div>
            <ul class="list-group list-group-flush">
                @foreach($module->courses as $course)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $course->title }} <span class="text-muted">({{ $course->year }})</span></span>
                        <a href="{{ route('course-content.admin', $course->course_id) }}"
                           class="btn btn-sm btn-outline-secondary py-0">
                            <i class="bi bi-gear"></i> إدارة المحتوى
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
