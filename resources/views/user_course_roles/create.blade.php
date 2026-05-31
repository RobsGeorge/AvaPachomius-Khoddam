@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">تعيين دور لمستخدم</h1>
        <a href="{{ route('user-course-roles.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> رجوع
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('user-course-roles.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="user_id" class="form-label fw-semibold">المستخدم <span class="text-danger">*</span></label>
                    <select name="user_id" id="user_id"
                            class="form-select @error('user_id') is-invalid @enderror" required>
                        <option value="">-- اختر المستخدم --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->user_id }}"
                                {{ old('user_id') == $user->user_id ? 'selected' : '' }}>
                                {{ $user->first_name }} {{ $user->second_name }}
                                ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="course_id" class="form-label fw-semibold">الدورة <span class="text-danger">*</span></label>
                    <select name="course_id" id="course_id"
                            class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">-- اختر الدورة --</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}"
                                {{ old('course_id') == $course->course_id ? 'selected' : '' }}>
                                {{ $course->title }} ({{ $course->year }})
                            </option>
                        @endforeach
                    </select>
                    @error('course_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="role_id" class="form-label fw-semibold">الدور <span class="text-danger">*</span></label>
                    <select name="role_id" id="role_id"
                            class="form-select @error('role_id') is-invalid @enderror" required>
                        <option value="">-- اختر الدور --</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->role_id }}"
                                {{ old('role_id') == $role->role_id ? 'selected' : '' }}>
                                {{ $role->role_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> تعيين الدور
                    </button>
                    <a href="{{ route('user-course-roles.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
