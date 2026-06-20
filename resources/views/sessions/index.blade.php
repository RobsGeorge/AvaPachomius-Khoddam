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

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
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
                        <th>{{ __('pages.attendance_status') }}</th>
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
                            <td>
                                @if($session->isAttendanceClosed())
                                    <span class="badge bg-success">{{ __('pages.attendance_status_closed') }}</span>
                                    @if($session->attendance_closed_at)
                                        <div class="small text-muted-theme mt-1">
                                            {{ $session->attendance_closed_at->format('Y-m-d H:i') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="badge bg-warning text-dark">{{ __('pages.attendance_status_open') }}</span>
                                @endif
                            </td>
                            @if(auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor'))
                                <td class="d-flex gap-2 flex-wrap">
                                    @php
                                        $sessionDate = $session->session_date?->format('Y-m-d');
                                        $canClose = ! $session->isAttendanceClosed()
                                            && $sessionDate
                                            && $sessionDate <= $todayLocal;
                                    @endphp
                                    @if($canClose)
                                        <form method="POST"
                                              action="{{ route('sessions.close-attendance', $session->session_id) }}"
                                              onsubmit="return confirm(@json(__('pages.confirm_close_attendance')))">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('pages.close_attendance') }}">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        </form>
                                    @endif
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
                            @php
                                $columnCount = (auth()->user()->roles->contains('role_name', 'admin') || auth()->user()->roles->contains('role_name', 'instructor')) ? 8 : 7;
                            @endphp
                            <td colspan="{{ $columnCount }}" class="text-center text-muted-theme py-4">
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

    @include('partials.pagination', ['paginator' => $sessions])
</div>
@endsection
