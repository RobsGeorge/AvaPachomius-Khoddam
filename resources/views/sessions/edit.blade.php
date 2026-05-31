@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">تعديل الجلسة</h1>
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
            <form method="POST" action="{{ route('sessions.update', $session->session_id) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label fw-semibold">الدورة <span class="text-danger">*</span></label>
                    <select name="course_id" class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">-- اختر الدورة --</option>
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
                    <label class="form-label fw-semibold">عنوان الجلسة <span class="text-danger">*</span></label>
                    <input type="text" name="session_title"
                           class="form-control @error('session_title') is-invalid @enderror"
                           value="{{ old('session_title', $session->session_title) }}"
                           maxlength="30" required>
                    @error('session_title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">تاريخ الجلسة <span class="text-danger">*</span></label>
                    <input type="date" name="session_date"
                           class="form-control @error('session_date') is-invalid @enderror"
                           value="{{ old('session_date', $session->session_date) }}" required>
                    @error('session_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> حفظ التعديلات
                    </button>
                    <a href="{{ route('sessions.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header fw-semibold text-muted">معلومات الجلسة</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">رقم الجلسة</dt>
                <dd class="col-sm-8">#{{ $session->session_id }}</dd>
                <dt class="col-sm-4">عدد سجلات الحضور</dt>
                <dd class="col-sm-8">{{ $session->attendances->count() }}</dd>
                <dt class="col-sm-4">تاريخ الإنشاء</dt>
                <dd class="col-sm-8">{{ $session->created_at->format('Y-m-d H:i') }}</dd>
            </dl>
        </div>
    </div>
</div>
@endsection
