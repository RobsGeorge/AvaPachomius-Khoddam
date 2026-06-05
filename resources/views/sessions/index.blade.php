@extends('layouts.app')

@section('title', __('pages.sessions'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('pages.sessions') }}</h1>
        @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
            <a href="{{ route('sessions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> {{ __('pages.create_sessions') }}
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.number') }}</th>
                        <th>{{ __('pages.date') }}</th>
                        <th>{{ __('pages.session_title') }}</th>
                        <th>{{ __('pages.course') }}</th>
                        <th>{{ __('pages.module') }}</th>
                        <th>{{ __('pages.attendance_count') }}</th>
                        @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                            <th>{{ __('pages.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr>
                            <td>{{ $sessions->firstItem() + $loop->index }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ \Carbon\Carbon::parse($session->session_date)->format('Y-m-d') }}
                                </span>
                            </td>
                            <td>{{ $session->session_title }}</td>
                            <td>{{ $session->course->title ?? '—' }}</td>
                            <td>{{ $session->module->title ?? '—' }}</td>
                            <td>
                                <span class="badge bg-secondary">
                                    {{ $session->attendances->count() }}
                                </span>
                            </td>
                            @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                                <td class="d-flex gap-2">
                                    <a href="{{ route('sessions.edit', $session->session_id) }}"
                                       class="btn btn-sm btn-outline-theme">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form method="POST" action="{{ route('sessions.destroy', $session->session_id) }}"
                                          onsubmit="return confirm(@json(__('pages.confirm_delete_session')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted-theme py-4">
                                {{ __('pages.no_sessions_yet') }}
                                @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                                    <a href="{{ route('sessions.create') }}">{{ __('pages.create_now') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-center">
        {{ $sessions->links() }}
    </div>
</div>
@endsection
