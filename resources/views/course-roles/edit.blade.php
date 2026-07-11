@extends('layouts.app')

@section('title', __('rbac.edit_role'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('rbac.edit_role') }}: {{ $role->role_name }}</h1>
        <a href="{{ route('courses.roles.index', $course) }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('courses.roles.update', [$course, $role]) }}">
        @csrf @method('PUT')
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('rbac.role_name') }}</label>
                        <input type="text" name="role_name" class="form-control" required value="{{ old('role_name', $role->role_name) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('rbac.description') }}</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description', $role->description) }}">
                    </div>
                </div>
            </div>
        </div>

        <h2 class="h5 mb-3">{{ __('rbac.permission_groups') }}</h2>

        @foreach($groups as $group)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>{{ $group->label() }}</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleGroup('group-{{ $group->permission_group_id }}')">
                        {{ __('rbac.permissions') }}
                    </button>
                </div>
                <div class="card-body" id="group-{{ $group->permission_group_id }}">
                    <div class="row">
                        @foreach($group->permissions->where('is_system_only', false) as $perm)
                            <div class="col-md-6 col-lg-4 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           value="{{ $perm->permission_id }}" id="perm-{{ $perm->permission_id }}"
                                           @checked(in_array($perm->permission_id, old('permissions', $assignedIds)))>
                                    <label class="form-check-label" for="perm-{{ $perm->permission_id }}">
                                        <strong>{{ $perm->label() }}</strong>
                                        <small class="d-block text-muted-theme">{{ $perm->key }}</small>
                                        @if($perm->route_names)
                                            <small class="d-block text-muted-theme">{{ __('rbac.endpoints') }}: {{ implode(', ', array_slice($perm->route_names, 0, 2)) }}{{ count($perm->route_names) > 2 ? '…' : '' }}</small>
                                        @endif
                                        @if($perm->nav_key)
                                            <small class="d-block text-muted-theme"><i class="bi bi-link-45deg"></i> {{ $perm->nav_key }}</small>
                                        @endif
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary btn-lg">{{ __('rbac.save') }}</button>
    </form>
</div>

@push('scripts')
<script>
function toggleGroup(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>
@endpush
@endsection
