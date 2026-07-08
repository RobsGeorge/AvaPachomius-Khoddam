@extends('layouts.app')

@section('title', __('pages.roles_management'))

@section('content')
<div class="container py-4 animate-in">
    <h1 class="page-title mb-4">{{ __('pages.roles_management') }}</h1>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card shadow-sm mb-5">
        <div class="card-header fw-semibold">{{ __('pages.roles_list') }}</div>
        <div class="card-body p-0">
            <div class="table-responsive d-none d-lg-block admin-table-desktop">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.number') }}</th>
                        <th>{{ __('pages.role_name') }}</th>
                        <th>{{ __('pages.description') }}</th>
                        <th>{{ __('pages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $role->role_name }}</td>
                            <td>{{ $role->role_decription }}</td>
                            <td>
                                <form method="POST" action="{{ route('roles.destroy', $role->role_id) }}"
                                      data-confirm="{{ __('pages.confirm_delete_role') }}"
                                      onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> {{ __('pages.delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted-theme py-4">{{ __('pages.no_roles_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div class="d-lg-none admin-data-cards student-data-hub p-3">
                @forelse($roles as $role)
                    <article class="data-card">
                        <div class="data-card-title">{{ $role->role_name }}</div>
                        <dl class="data-meta-list mb-3">
                            <div class="data-meta-row">
                                <dt>{{ __('pages.number') }}</dt>
                                <dd>{{ $loop->iteration }}</dd>
                            </div>
                            <div class="data-meta-row">
                                <dt>{{ __('pages.description') }}</dt>
                                <dd>{{ $role->role_decription }}</dd>
                            </div>
                        </dl>
                        <div class="data-card-actions">
                            <form method="POST" action="{{ route('roles.destroy', $role->role_id) }}"
                                  data-confirm="{{ __('pages.confirm_delete_role') }}"
                                  onsubmit="return confirm(this.dataset.confirm)">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger w-100">
                                    <i class="bi bi-trash"></i> {{ __('pages.delete') }}
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="text-center text-muted-theme py-4 mb-0">{{ __('pages.no_roles_yet') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-header fw-semibold">{{ __('pages.add_new_role') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('pages.role_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="role_name"
                               class="form-control @error('role_name') is-invalid @enderror"
                               value="{{ old('role_name') }}" maxlength="30" required>
                        @error('role_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('pages.description') }} <span class="text-danger">*</span></label>
                        <input type="text" name="role_decription"
                               class="form-control @error('role_decription') is-invalid @enderror"
                               value="{{ old('role_decription') }}" maxlength="25" required>
                        @error('role_decription')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> {{ __('pages.add') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('user-course-roles.index') }}" class="btn btn-outline-theme">
            <i class="bi bi-people"></i> {{ __('pages.assign_roles_to_users') }}
        </a>
    </div>
</div>
@endsection
