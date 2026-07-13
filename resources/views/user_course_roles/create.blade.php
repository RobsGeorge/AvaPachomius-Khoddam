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
                        @foreach($courses as $course)
                            @php $courseRoles = $rolesByCourse->get($course->course_id, collect()); @endphp
                            @if($courseRoles->isNotEmpty())
                                <optgroup label="{{ $course->title }} ({{ $course->year }})">
                                    @foreach($courseRoles as $role)
                                        <option value="{{ $role->role_id }}"
                                            data-course-id="{{ $course->course_id }}"
                                            {{ (string) old('role_id') === (string) $role->role_id ? 'selected' : '' }}>
                                            {{ $role->role_name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const courseSelect = document.getElementById('course_id');
    const roleSelect = document.getElementById('role_id');
    if (!courseSelect || !roleSelect) {
        return;
    }

    const filterRoles = () => {
        const courseId = courseSelect.value;
        let firstVisible = '';

        Array.from(roleSelect.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const matches = !courseId || option.dataset.courseId === courseId;
            option.hidden = !matches;

            if (matches && !firstVisible) {
                firstVisible = option.value;
            }
        });

        const selected = roleSelect.selectedOptions[0];
        if (selected && selected.hidden) {
            roleSelect.value = firstVisible;
        }
    };

    courseSelect.addEventListener('change', filterRoles);
    filterRoles();
});
</script>
@endsection
