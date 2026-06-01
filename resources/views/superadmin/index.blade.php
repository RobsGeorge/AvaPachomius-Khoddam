@extends('layouts.app')

@section('title', __('pages.superadmin_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> {{ __('pages.superadmin_role') }}
        </span>
        <h1 class="page-title mb-0">{{ __('pages.roles_permissions') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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
                                              onsubmit="return confirm(@json(__('pages.confirm_delete_course')))">
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
                            <select name="course_id" class="form-select" required @disabled($courses->isEmpty())>
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
                            <select name="role_id" class="form-select" required>
                                <option value="">{{ __('pages.select_option') }}</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}"
                                        {{ old('role_id') == $role->role_id ? 'selected' : '' }}>
                                        {{ $role->role_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger w-100" @disabled($courses->isEmpty() || $roles->isEmpty())>
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
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>{{ __('pages.name') }}</th><th>{{ __('pages.description') }}</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($roles as $role)
                                <tr>
                                    <td>{{ $role->role_name }}</td>
                                    <td class="text-muted-theme small">{{ $role->role_decription }}</td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('superadmin.roles.destroy', $role->role_id) }}"
                                              onsubmit="return confirm(@json(__('pages.confirm_delete_role_super')))">
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
                <div class="card-footer">
                    <form method="POST" action="{{ route('superadmin.roles.store') }}" class="d-flex gap-2">
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
                    <div class="table-responsive">
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
                                                  onsubmit="return confirm(@json(__('pages.confirm_cancel_role')))">
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
@endsection
