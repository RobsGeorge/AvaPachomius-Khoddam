@extends('layouts.app')

@section('title', __('pages.superadmin_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> {{ __('pages.superadmin_role') }}
        </span>
        <h1 class="page-title mb-0">{{ __('pages.roles_permissions') }}</h1>
        <a href="{{ route('superadmin.audit.index') }}" class="btn btn-outline-danger btn-sm ms-auto">
            <i class="bi bi-journal-text"></i> {{ __('pages.view_audit_reports') }}
        </a>
        <a href="{{ route('superadmin.events.tests.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-bug"></i> {{ __('events.tests_dashboard') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card border-danger shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 text-danger mb-2">
                <i class="bi bi-box-arrow-right"></i> {{ __('pages.force_logout_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.force_logout_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.sessions.flush-all') }}"
                  data-confirm="{{ __('pages.force_logout_confirm') }}"
                  onsubmit="return confirm(this.dataset.confirm);">
                @csrf
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-power"></i> {{ __('pages.force_logout_button') }}
                </button>
            </form>
        </div>
    </div>

    <div class="app-card card border-warning shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 text-warning mb-2">
                <i class="bi bi-eye-fill"></i> {{ __('pages.impersonate_title') }}
            </h2>
            <p class="text-muted mb-3">{{ __('pages.impersonate_hint') }}</p>
            <form method="POST"
                  action="{{ route('superadmin.impersonate') }}"
                  data-confirm="{{ __('pages.impersonate_confirm') }}"
                  onsubmit="return confirm(this.dataset.confirm);">
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

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($courses->isEmpty())
        <div class="alert alert-warning d-flex gap-3 align-items-start mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
            <div>
                <strong>{{ __('pages.create_first_course') }}</strong>
                <p class="mb-1 mt-1">{{ __('pages.create_first_course_hint') }}</p>
                <p class="mb-0 small text-muted-theme">{{ __('pages.setup_order_hint') }}</p>
            </div>
        </div>
    @else
        <p class="text-muted-theme small mb-4">{{ __('pages.setup_order_hint') }}</p>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="app-card card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white fw-semibold">
                    <i class="bi bi-journal-bookmark-fill"></i> {{ __('pages.manage_courses') }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-compact">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.course_title') }}</th>
                                <th>{{ __('pages.year') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($courses as $course)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $course->title }}</div>
                                        <div class="text-muted-theme small text-truncate" style="max-width:160px;" title="{{ $course->description }}">
                                            {{ $course->description }}
                                        </div>
                                    </td>
                                    <td>{{ $course->year }}</td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('superadmin.courses.destroy', $course->course_id) }}"
                                              data-confirm="{{ __('pages.confirm_delete_course') }}"
                                              onsubmit="return confirm(this.dataset.confirm)">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger py-0 px-1">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted-theme py-3">
                                        {{ __('pages.no_courses_yet') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
                <div class="card-footer">
                    <form method="POST" action="{{ route('superadmin.courses.store') }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">{{ __('pages.course_title') }}</label>
                            <input type="text" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" maxlength="30"
                                   placeholder="{{ __('pages.course_title_placeholder') }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">{{ __('pages.description') }}</label>
                            <textarea name="description" rows="2" class="form-control form-control-sm @error('description') is-invalid @enderror"
                                      maxlength="255" placeholder="{{ __('pages.course_description_placeholder') }}" required>{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold mb-1">{{ __('pages.year') }}</label>
                            <input type="number" name="year" class="form-control form-control-sm @error('year') is-invalid @enderror"
                                   value="{{ old('year', date('Y')) }}" min="2000" max="2100" required>
                            @error('year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold mb-1">{{ __('pages.default_session_start_time') }}</label>
                            <input type="time" name="default_session_start_time"
                                   class="form-control form-control-sm @error('default_session_start_time') is-invalid @enderror"
                                   value="{{ old('default_session_start_time', '09:00') }}" required>
                            <div class="form-text">{{ __('pages.course_default_session_start_time_hint') }}</div>
                            @error('default_session_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-sm">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.create_course') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="app-card card shadow-sm mb-4 border-danger">
                <div class="card-header bg-danger text-white fw-semibold">
                    <i class="bi bi-person-plus-fill"></i> {{ __('pages.assign_role_to_user') }}
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('superadmin.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.user') }}</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">{{ __('pages.select_option') }}</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->user_id }}"
                                        {{ old('user_id') == $user->user_id ? 'selected' : '' }}>
                                        {{ $user->first_name }} {{ $user->second_name }}
                                        ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.course') }}</label>
                            <select name="course_id" id="superadmin-course-id" class="form-select" required @disabled($courses->isEmpty())>
                                <option value="">{{ __('pages.select_course') }}</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->course_id }}"
                                        {{ old('course_id') == $course->course_id ? 'selected' : '' }}>
                                        {{ $course->title }} ({{ $course->year }})
                                    </option>
                                @endforeach
                            </select>
                            @if($courses->isEmpty())
                                <div class="form-text text-warning">{{ __('pages.create_first_course_hint') }}</div>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">{{ __('pages.role') }}</label>
                            <select name="role_id" id="superadmin-role-id" class="form-select" required>
                                <option value="">{{ __('pages.select_option') }}</option>
                                @foreach($courses as $course)
                                    @php $courseRoles = $rolesByCourse->get($course->course_id, collect()); @endphp
                                    @if($courseRoles->isNotEmpty())
                                        <optgroup label="{{ $course->title }} ({{ $course->year }})" data-course-id="{{ $course->course_id }}">
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
                            <div class="form-text">{{ __('pages.course_role_assignment_hint') }}</div>
                        </div>
                        <button type="submit" class="btn btn-danger w-100" @disabled($courses->isEmpty())>
                            <i class="bi bi-check-circle"></i> {{ __('pages.assign_role') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="app-card card shadow-sm border-secondary">
                <div class="card-header fw-semibold">
                    <i class="bi bi-shield"></i> {{ __('pages.available_roles') }}
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-compact">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>{{ __('pages.name') }}</th><th>{{ __('pages.description') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($legacyRoles as $role)
                                <tr>
                                    <td>{{ $role->role_name }}</td>
                                    <td class="text-muted-theme small">{{ $role->role_decription }}</td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('superadmin.roles.destroy', $role->role_id) }}"
                                              data-confirm="{{ __('pages.confirm_delete_role_super') }}"
                                              onsubmit="return confirm(this.dataset.confirm)">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-outline-danger py-0 px-1">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted-theme py-2">{{ __('pages.no_roles_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
                <div class="card-footer">
                    <form method="POST" action="{{ route('superadmin.roles.store') }}" class="d-flex gap-2 flex-wrap">
                        @csrf
                        <input type="text" name="role_name" class="form-control form-control-sm"
                               placeholder="{{ __('pages.role_name_placeholder') }}" maxlength="30" required>
                        <input type="text" name="role_decription" class="form-control form-control-sm"
                               placeholder="{{ __('pages.role_desc_placeholder') }}" maxlength="25" required>
                        <button type="submit" class="btn btn-sm btn-outline-theme text-nowrap">
                            <i class="bi bi-plus"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="app-card card shadow-sm mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-calendar-event"></i> {{ __('events.event_admins_title') }}
                </div>
                <div class="card-body p-0">
                    <p class="small text-muted-theme px-3 pt-3 mb-2">{{ __('events.event_admins_hint') }}</p>
                    <div class="table-responsive table-responsive-compact">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr><th>{{ __('pages.user') }}</th><th></th></tr>
                            </thead>
                            <tbody>
                                @forelse($eventAdmins as $ea)
                                    <tr>
                                        <td>
                                            {{ $ea->user->first_name ?? '—' }} {{ $ea->user->second_name ?? '' }}
                                            <div class="text-muted small">{{ $ea->user->email ?? '—' }}</div>
                                        </td>
                                        <td>
                                            <form method="POST"
                                                  action="{{ route('superadmin.event-admins.destroy', $ea->user_id) }}"
                                                  data-confirm="{{ __('pages.confirm_delete') }}"
                                                  onsubmit="return confirm(this.dataset.confirm)">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-xs btn-outline-danger py-0 px-1">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-muted-theme py-2">—</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <form method="POST" action="{{ route('superadmin.event-admins.store') }}" class="d-flex gap-2">
                        @csrf
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">{{ __('pages.select_option') }}</option>
                            @foreach($users as $u)
                                <option value="{{ $u->user_id }}">{{ $u->first_name }} {{ $u->second_name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-theme text-nowrap">
                            <i class="bi bi-plus"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="app-card card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-people-fill"></i>
                    {{ __('pages.all_role_assignments') }}
                    <span class="badge bg-secondary ms-1">{{ $assignments->count() }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-compact">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('pages.user') }}</th>
                                    <th>{{ __('pages.email') }}</th>
                                    <th>{{ __('pages.course') }}</th>
                                    <th>{{ __('pages.role') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $a)
                                    <tr>
                                        <td>
                                            {{ $a->user->first_name ?? '—' }}
                                            {{ $a->user->second_name ?? '' }}
                                            @if($a->user->is_superadmin ?? false)
                                                <span class="badge bg-danger ms-1" title="{{ __('pages.superadmin_role') }}">
                                                    <i class="bi bi-shield-lock-fill"></i>
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-muted-theme small">{{ $a->user->email ?? '—' }}</td>
                                        <td>{{ $a->course->title ?? '—' }}</td>
                                        <td>
                                            <span class="badge bg-primary">
                                                {{ $a->role->role_name ?? '—' }}
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST"
                                                  action="{{ route('superadmin.destroy', $a->user_course_role_id) }}"
                                                  data-confirm="{{ __('pages.confirm_cancel_role') }}"
                                                  onsubmit="return confirm(this.dataset.confirm)">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted-theme py-4">
                                            {{ __('pages.no_role_assignments') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const courseSelect = document.getElementById('superadmin-course-id');
    const roleSelect = document.getElementById('superadmin-role-id');
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
