@extends('layouts.app')

@section('title', __('rbac.title_course', ['course' => $course->title]))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('rbac.title_course', ['course' => $course->title]) }}</h1>
        <a href="{{ route('hubs.system') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="app-card card shadow-sm mb-4">
                <div class="card-header fw-semibold">{{ __('rbac.create_role') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('courses.roles.store', $course) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('rbac.role_name') }}</label>
                            <input type="text" name="role_name" class="form-control" required maxlength="30" value="{{ old('role_name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('rbac.description') }}</label>
                            <input type="text" name="description" class="form-control" maxlength="255" value="{{ old('description') }}">
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('rbac.create_role') }}</button>
                    </form>
                </div>
            </div>

            <div class="app-card card shadow-sm mb-4">
                <div class="card-header fw-semibold">{{ __('rbac.copy_roles') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('courses.roles.copy', $course) }}">
                        @csrf
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="use_templates" value="1" id="use_templates">
                            <label class="form-check-label" for="use_templates">{{ __('rbac.use_templates') }}</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('rbac.source_course') }}</label>
                            <select name="source_course_id" class="form-select">
                                <option value="">{{ __('rbac.copy_from_course') }}</option>
                                @foreach(\App\Models\Course::where('course_id', '!=', $course->course_id)->orderBy('title')->get() as $c)
                                    <option value="{{ $c->course_id }}">{{ $c->title }} ({{ $c->year }})</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">{{ __('rbac.copy_roles') }}</button>
                    </form>
                </div>
            </div>

            <div class="app-card card shadow-sm">
                <div class="card-header fw-semibold">{{ __('rbac.assign_user') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('courses.roles.assignments.store', $course) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('rbac.user') }}</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">{{ __('rbac.user') }}</option>
                                @foreach(\App\Models\User::orderBy('first_name')->get() as $u)
                                    <option value="{{ $u->user_id }}">{{ $u->displayName() }} — {{ $u->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('rbac.role') }}</label>
                            <select name="role_id" class="form-select" required>
                                @foreach($roles as $r)
                                    <option value="{{ $r->role_id }}">{{ $r->role_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('rbac.assign_user') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="app-card card shadow-sm mb-4">
                <div class="card-header fw-semibold">{{ __('rbac.roles_list') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('rbac.role_name') }}</th>
                                    <th>{{ __('rbac.description') }}</th>
                                    <th>{{ __('rbac.users_count', ['count' => '']) }}</th>
                                    <th>{{ __('rbac.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($roles as $role)
                                    <tr>
                                        <td>{{ $role->role_name }}</td>
                                        <td>{{ $role->description ?? $role->role_decription }}</td>
                                        <td>{{ $role->user_course_roles_count }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('courses.roles.edit', [$course, $role]) }}" class="btn btn-sm btn-outline-primary">{{ __('rbac.permissions') }}</a>
                                            @if($role->user_course_roles_count === 0)
                                                <form method="POST" action="{{ route('courses.roles.destroy', [$course, $role]) }}" class="d-inline" onsubmit="return confirm(@json(__('rbac.confirm_delete')))">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted-theme py-4">{{ __('rbac.no_roles') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="app-card card shadow-sm">
                <div class="card-header fw-semibold">{{ __('rbac.assignments') }}</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('rbac.user') }}</th>
                                    <th>{{ __('rbac.role') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assignments as $a)
                                    <tr>
                                        <td>{{ $a->user?->displayName() }}</td>
                                        <td>{{ $a->role?->role_name }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('courses.roles.assignments.destroy', [$course, $a]) }}" class="d-inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted-theme py-4">—</td></tr>
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
