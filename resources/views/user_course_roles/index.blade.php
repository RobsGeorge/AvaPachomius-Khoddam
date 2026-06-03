@extends('layouts.app')

@section('title', __('pages.assign_roles'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title mb-0">{{ __('pages.assign_roles') }}</h1>
        <a href="{{ route('user-course-roles.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> {{ __('pages.assign_role_to_user') }} {{ __('pages.new_assignment') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.number') }}</th>
                        <th>{{ __('pages.user') }}</th>
                        <th>{{ __('pages.email') }}</th>
                        <th>{{ __('pages.course') }}</th>
                        <th>{{ __('pages.role') }}</th>
                        <th>{{ __('pages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                {{ $assignment->user?->first_name ?? '—' }}
                                {{ $assignment->user?->second_name ?? '' }}
                            </td>
                            <td>{{ $assignment->user?->email ?? '—' }}</td>
                            <td>{{ $assignment->course?->title ?? '—' }}</td>
                            <td>
                                <span class="badge bg-primary">
                                    {{ $assignment->role?->role_name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <form method="POST"
                                      action="{{ route('user-course-roles.destroy', $assignment->user_course_role_id) }}"
                                      onsubmit="return confirm(@json(__('pages.confirm_cancel_assignment')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-x-circle"></i> {{ __('pages.cancel') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted-theme py-4">{{ __('pages.no_role_assignments') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{ route('roles.index') }}" class="btn btn-outline-theme">
            <i class="bi bi-shield"></i> {{ __('pages.manage_roles') }}
        </a>
    </div>
</div>
@endsection
