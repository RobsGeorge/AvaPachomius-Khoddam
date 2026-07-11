@extends('layouts.app')

@section('title', __('rbac.system_roles'))

@section('content')
<div class="container py-4">
    <h1 class="page-title mb-4">{{ __('rbac.system_roles') }}</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="app-card card shadow-sm">
                <div class="card-header">{{ __('rbac.create_role') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('superadmin.system-roles.store') }}">
                        @csrf
                        <div class="mb-3">
                            <input type="text" name="role_name" class="form-control" placeholder="{{ __('rbac.role_name') }}" required>
                        </div>
                        <div class="mb-3">
                            <input type="text" name="description" class="form-control" placeholder="{{ __('rbac.description') }}">
                        </div>
                        @foreach($groups as $group)
                            <h6>{{ $group->label() }}</h6>
                            @foreach($group->permissions as $perm)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $perm->permission_id }}">
                                    <label class="form-check-label">{{ $perm->label() }}</label>
                                </div>
                            @endforeach
                        @endforeach
                        <button type="submit" class="btn btn-primary mt-3">{{ __('rbac.create_role') }}</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="app-card card shadow-sm mb-4">
                <div class="card-header">{{ __('rbac.assign_user') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('superadmin.system-roles.assign') }}">
                        @csrf
                        <select name="user_id" class="form-select mb-2" required>
                            @foreach(\App\Models\User::orderBy('first_name')->get() as $u)
                                <option value="{{ $u->user_id }}">{{ $u->displayName() }}</option>
                            @endforeach
                        </select>
                        <select name="role_id" class="form-select mb-2" required>
                            @foreach($roles as $r)
                                <option value="{{ $r->role_id }}">{{ $r->role_name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">{{ __('rbac.assign_user') }}</button>
                    </form>
                </div>
            </div>
            <div class="app-card card shadow-sm">
                <div class="card-header">{{ __('rbac.assignments') }}</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        @foreach($assignments as $a)
                            <tr>
                                <td>{{ $a->user?->displayName() }}</td>
                                <td>{{ $a->role?->role_name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('superadmin.system-roles.assignments.destroy', $a) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">{{ __('rbac.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
