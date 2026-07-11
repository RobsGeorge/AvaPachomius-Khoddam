@extends('layouts.app')

@section('title', __('rbac.manage_templates'))

@section('content')
<div class="container py-4">
    <h1 class="page-title mb-4">{{ __('rbac.manage_templates') }}</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @foreach($templates as $template)
        <div class="app-card card shadow-sm mb-4">
            <div class="card-header fw-semibold">{{ $template->role_name }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('superadmin.templates.update', $template) }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <input type="text" name="role_name" class="form-control" value="{{ $template->role_name }}" required>
                    </div>
                    <div class="mb-3">
                        <input type="text" name="description" class="form-control" value="{{ $template->description }}" placeholder="{{ __('rbac.description') }}">
                    </div>
                    @foreach($groups as $group)
                        <h6 class="mt-3">{{ $group->label() }}</h6>
                        <div class="row">
                            @foreach($group->permissions as $perm)
                                <div class="col-md-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->permission_id }}"
                                               id="t-{{ $template->role_id }}-{{ $perm->permission_id }}"
                                               @checked($template->permissions->contains('permission_id', $perm->permission_id))>
                                        <label class="form-check-label" for="t-{{ $template->role_id }}-{{ $perm->permission_id }}">{{ $perm->label() }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <button type="submit" class="btn btn-primary mt-3">{{ __('rbac.save') }}</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
