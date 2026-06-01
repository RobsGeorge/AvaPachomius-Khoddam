@extends('layouts.app')

@section('title', __('pages.assign_role_to_user'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.assign_role_to_user') }}</h1>
        <a href="{{ route('user-course-roles.index') }}" class="btn btn-outline-theme">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back') }}
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

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('user-course-roles.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="user_id" class="form-label fw-semibold">{{ __('pages.user') }} <span class="text-danger">*</span></label>
                    <select name="user_id" id="user_id"
                            class="form-select @error('user_id') is-invalid @enderror" required>
                        <option value="">{{ __('pages.select_user') }}</option>
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
                    <label for="course_id" class="form-label fw-semibold">{{ __('pages.course') }} <span class="text-danger">*</span></label>
                    <select name="course_id" id="course_id"
                            class="form-select @error('course_id') is-invalid @enderror" required>
                        <option value="">{{ __('pages.select_course') }}</option>
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
                    <label for="role_id" class="form-label fw-semibold">{{ __('pages.role') }} <span class="text-danger">*</span></label>
                    <select name="role_id" id="role_id"
                            class="form-select @error('role_id') is-invalid @enderror" required>
                        <option value="">{{ __('pages.select_role') }}</option>
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
                        <i class="bi bi-check-circle"></i> {{ __('pages.assign_role') }}
                    </button>
                    <a href="{{ route('user-course-roles.index') }}" class="btn btn-outline-theme">{{ __('pages.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
