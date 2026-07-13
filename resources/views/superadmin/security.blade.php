@extends('layouts.app')

@section('title', __('pages.superadmin_security_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    @include('superadmin.partials.header', ['title' => __('pages.superadmin_security_title')])

    <div class="app-card card border-danger shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 text-danger mb-2">
                <i class="bi bi-box-arrow-right"></i> {{ __('pages.force_logout_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.force_logout_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.sessions.flush-all') }}"
                  data-confirm="{{ __('pages.force_logout_confirm') }}">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-power"></i> {{ __('pages.force_logout_button') }}
                </button>
            </form>
        </div>
    </div>

    <div class="app-card card border-info shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 text-info mb-2">
                <i class="bi bi-person-badge-fill"></i> {{ __('pages.role_preview_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.role_preview_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.role-preview') }}"
                  data-confirm="{{ __('pages.role_preview_confirm') }}">
                @csrf
                <div class="form-check mb-3">
                    <input class="form-check-input"
                           type="checkbox"
                           name="general_role"
                           value="1"
                           id="role-preview-general"
                           {{ old('general_role') ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="role-preview-general">
                        {{ __('pages.role_preview_general_role') }}
                    </label>
                    <div class="form-text">{{ __('pages.role_preview_general_hint') }}</div>
                </div>
                <div class="mb-3" id="role-preview-course-wrap">
                    <label class="form-label fw-semibold" for="role-preview-course-id">{{ __('pages.course') }}</label>
                    <select name="course_id" id="role-preview-course-id" class="form-select" @disabled($courses->isEmpty())>
                        <option value="">{{ __('pages.select_course') }}</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->course_id }}"
                                {{ (string) old('course_id') === (string) $course->course_id ? 'selected' : '' }}>
                                {{ $course->title }} ({{ $course->year }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="role-preview-role-id">{{ __('pages.role') }}</label>
                    <select name="role_id" id="role-preview-role-id" class="form-select" required>
                        <option value="">{{ __('pages.select_option') }}</option>
                        <optgroup label="{{ __('pages.role_preview_general_roles') }}" id="role-preview-system-roles" hidden>
                            @foreach($systemRoles as $role)
                                <option value="{{ $role->role_id }}"
                                    data-general="1"
                                    {{ (string) old('role_id') === (string) $role->role_id ? 'selected' : '' }}>
                                    {{ $role->role_name }}
                                </option>
                            @endforeach
                        </optgroup>
                        @foreach($courses as $course)
                            @php $courseRoles = $rolesByCourse->get($course->course_id, collect()); @endphp
                            @if($courseRoles->isNotEmpty())
                                <optgroup label="{{ $course->title }} ({{ $course->year }})"
                                          data-course-id="{{ $course->course_id }}"
                                          class="role-preview-course-group">
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
                </div>
                @error('role_id')
                    <div class="text-danger small mb-3">{{ $message }}</div>
                @enderror
                @error('course_id')
                    <div class="text-danger small mb-3">{{ $message }}</div>
                @enderror
                <button type="submit" class="btn btn-info text-dark">
                    <i class="bi bi-box-arrow-in-right"></i> {{ __('pages.role_preview_button') }}
                </button>
            </form>
        </div>
    </div>

    <div class="app-card card border-warning shadow-sm">
        <div class="card-body">
            <h2 class="h5 text-warning mb-2">
                <i class="bi bi-eye-fill"></i> {{ __('pages.impersonate_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.impersonate_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.impersonate') }}"
                  data-confirm="{{ __('pages.impersonate_confirm') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="impersonate-user-id">{{ __('pages.user') }}</label>
                    <select name="user_id" id="impersonate-user-id" class="form-select" required>
                        <option value="">{{ __('pages.select_option') }}</option>
                        @foreach($users as $user)
                            @php
                                $roleNames = $user->roles->pluck('role_name')->unique();
                                if ($user->is_superadmin) {
                                    $roleNames = $roleNames->push(__('pages.superadmin_role'));
                                }
                                $roleLabel = $roleNames->isNotEmpty()
                                    ? $roleNames->implode(', ')
                                    : __('pages.no_roles_yet');
                            @endphp
                            <option value="{{ $user->user_id }}"
                                {{ old('user_id') == $user->user_id ? 'selected' : '' }}>
                                {{ $user->first_name }} {{ $user->second_name }}
                                ({{ $user->email }}) — {{ $roleLabel }}
                            </option>
                        @endforeach
                    </select>
                    @error('user')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-box-arrow-in-right"></i> {{ __('pages.impersonate_button') }}
                </button>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const generalCheckbox = document.getElementById('role-preview-general');
    const courseWrap = document.getElementById('role-preview-course-wrap');
    const courseSelect = document.getElementById('role-preview-course-id');
    const roleSelect = document.getElementById('role-preview-role-id');
    const systemGroup = document.getElementById('role-preview-system-roles');

    if (!generalCheckbox || !courseSelect || !roleSelect) {
        return;
    }

    const syncRolePreviewForm = () => {
        const isGeneral = generalCheckbox.checked;

        courseWrap.classList.toggle('opacity-50', isGeneral);
        courseSelect.disabled = isGeneral;
        courseSelect.required = !isGeneral;
        if (isGeneral) {
            courseSelect.value = '';
        }

        systemGroup.hidden = !isGeneral;

        Array.from(roleSelect.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            if (option.dataset.general === '1') {
                option.hidden = !isGeneral;
                return;
            }

            option.hidden = isGeneral || (
                !isGeneral
                && courseSelect.value
                && option.dataset.courseId !== courseSelect.value
            );
        });

        Array.from(document.querySelectorAll('.role-preview-course-group')).forEach((group) => {
            group.hidden = isGeneral || (
                courseSelect.value && group.dataset.courseId !== courseSelect.value
            );
        });

        const selected = roleSelect.selectedOptions[0];
        if (selected && selected.hidden) {
            roleSelect.value = '';
        }
    };

    generalCheckbox.addEventListener('change', syncRolePreviewForm);
    courseSelect.addEventListener('change', syncRolePreviewForm);
    syncRolePreviewForm();
});
</script>
@endsection
