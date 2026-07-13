@extends('layouts.app')

@section('title', __('rbac.edit_role'))

@section('content')
@php
    $hub = app(\App\Services\RolesHubService::class);
@endphp
<div class="container py-3 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title mb-0 h5">{{ __('rbac.edit_role') }}: {{ $role->role_name }}</h1>
            <p class="text-muted-theme small mb-0">{{ $course->title }}</p>
        </div>
        <a href="{{ $hub->hubUrl($course, 'course') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back') }}</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger py-2 small">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('courses.roles.update', [$course, $role]) }}">
        @csrf @method('PUT')
        <details class="roles-hub-panel mb-2" open>
            <summary class="roles-hub-summary">{{ __('rbac.role_name') }}</summary>
            <div class="row g-2 pt-2">
                <div class="col-md-6">
                    <label class="form-label small mb-1">{{ __('rbac.role_name') }}</label>
                    <input type="text" name="role_name" class="form-control form-control-sm" required value="{{ old('role_name', $role->role_name) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small mb-1">{{ __('rbac.description') }}</label>
                    <input type="text" name="description" class="form-control form-control-sm" value="{{ old('description', $role->description) }}">
                </div>
            </div>
        </details>

        @foreach($groups as $group)
            <details class="roles-hub-panel mb-2">
                <summary class="roles-hub-summary">{{ $group->label() }} ({{ $group->permissions->where('is_system_only', false)->count() }})</summary>
                <div class="row pt-2">
                    @foreach($group->permissions->where('is_system_only', false) as $perm)
                        <div class="col-md-6 col-lg-4 mb-1">
                            <div class="form-check form-check-sm">
                                <input class="form-check-input" type="checkbox" name="permissions[]"
                                       value="{{ $perm->permission_id }}" id="perm-{{ $perm->permission_id }}"
                                       @checked(in_array($perm->permission_id, old('permissions', $assignedIds)))>
                                <label class="form-check-label small" for="perm-{{ $perm->permission_id }}">
                                    <strong>{{ $perm->label() }}</strong>
                                    <span class="d-block text-muted-theme">{{ $perm->key }}</span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endforeach

        <button type="submit" class="btn btn-primary btn-sm">{{ __('rbac.save') }}</button>
    </form>
</div>

@push('styles')
<style>
.roles-hub-panel { border: 1px solid var(--border-subtle, #dee2e6); border-radius: 0.375rem; padding: 0.35rem 0.65rem; background: var(--card-bg, #fff); }
.roles-hub-summary { cursor: pointer; font-weight: 600; font-size: 0.875rem; list-style: none; }
.roles-hub-summary::-webkit-details-marker { display: none; }
</style>
@endpush
@endsection
