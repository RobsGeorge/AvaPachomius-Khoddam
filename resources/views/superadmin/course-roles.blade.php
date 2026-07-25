@extends('layouts.app')

@section('title', __('pages.all_role_assignments'))

@section('content')
<div class="container py-4 animate-in">
    @include('superadmin.partials.header', ['title' => __('pages.all_role_assignments')])

    <div class="row g-4">
        <div class="col-lg-4">
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
                    <span class="badge bg-secondary ms-1">{{ __('pages.legacy_roles_badge') }}</span>
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
                                                  data-confirm="{{ __('pages.confirm_delete_role_super') }}">
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
                                                  data-confirm="{{ __('pages.confirm_cancel_role') }}">
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
