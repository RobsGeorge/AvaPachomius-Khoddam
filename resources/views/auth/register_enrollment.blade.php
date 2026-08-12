@extends('layouts.app')

@section('title', __('register.enrollment_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:560px;">
    <h2 class="page-title mb-2">{{ __('register.enrollment_title') }}</h2>
    <p class="text-muted mb-4">{{ __('register.enrollment_intro') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card">
        <div class="card-body p-4">
            <form action="{{ route('register.enrollment.store') }}" method="POST" id="enrollment-form">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user_id }}">

                <div class="mb-3">
                    <label for="service_id" class="form-label">{{ __('register.enrollment_service_label') }}</label>
                    <select
                        name="service_id"
                        id="service_id"
                        class="form-select @error('service_id') is-invalid @enderror"
                        required
                    >
                        @foreach ($services as $service)
                            <option
                                value="{{ $service->service_id }}"
                                @selected((int) $selectedServiceId === (int) $service->service_id)
                            >
                                {{ $service->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('service_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="course_id" class="form-label">{{ __('register.enrollment_course_label') }}</label>
                    <select
                        name="course_id"
                        id="course_id"
                        class="form-select @error('course_id') is-invalid @enderror"
                        required
                    >
                        <option value="">{{ __('register.enrollment_course_placeholder') }}</option>
                    </select>
                    @error('course_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('register.enrollment_submit') }}</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const coursesByService = @json($coursesByService);
    const serviceSelect = document.getElementById('service_id');
    const courseSelect = document.getElementById('course_id');
    const initialCourseId = @json($selectedCourseId);

    function populateCourses(serviceId, selectedCourseId) {
        const courses = coursesByService[serviceId] || [];
        courseSelect.innerHTML = '';

        if (courses.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = @json(__('register.enrollment_no_courses_for_service'));
            courseSelect.appendChild(option);
            courseSelect.disabled = true;
            return;
        }

        courseSelect.disabled = false;

        if (courses.length > 1) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = @json(__('register.enrollment_course_placeholder'));
            courseSelect.appendChild(placeholder);
        }

        courses.forEach(function (course) {
            const option = document.createElement('option');
            option.value = course.id;
            option.textContent = course.title;
            if (String(selectedCourseId) === String(course.id)) {
                option.selected = true;
            }
            courseSelect.appendChild(option);
        });

        if (courses.length === 1 && !selectedCourseId) {
            courseSelect.value = String(courses[0].id);
        }
    }

    serviceSelect.addEventListener('change', function () {
        populateCourses(this.value, null);
    });

    populateCourses(serviceSelect.value, initialCourseId);
});
</script>
@endsection
