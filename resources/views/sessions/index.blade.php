@extends('layouts.app')

@section('title', __('pages.sessions'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('pages.sessions') }}</h1>
        @if($canManageSessions)
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

    @if($canManageSessions)
    <div class="app-card card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive d-none d-lg-block admin-table-desktop">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('pages.number') }}</th>
                        <th>{{ __('pages.date') }}</th>
                        <th>{{ __('pages.session_title') }}</th>
                        <th>{{ __('pages.course') }}</th>
                        <th>{{ __('pages.module') }}</th>
                        @if($canManageSessions)
                            <th>{{ __('pages.attendance_count') }}</th>
                            <th>{{ __('pages.attendance_status') }}</th>
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
                            @if($canManageSessions)
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ $session->attended_count }}
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
                                <td class="d-flex gap-2 flex-wrap">
                                    <a href="{{ route('attendance.all', ['filter_by' => 'session', 'session_id' => $session->session_id]) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       title="{{ __('pages.view_session_roster') }}">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    @if(($missingCounts[$session->session_id] ?? 0) > 0)
                                        <span class="badge bg-warning text-dark align-self-center"
                                              title="{{ __('pages.roster_missing') }}">
                                            {{ __('pages.missing_records_count', ['count' => $missingCounts[$session->session_id]]) }}
                                        </span>
                                    @endif
                                    @php
                                        $sessionDate = $session->session_date?->format('Y-m-d');
                                        $canClose = ! $session->isAttendanceClosed()
                                            && $sessionDate
                                            && $sessionDate <= $todayLocal;
                                    @endphp
                                    @if($canClose)
                                        <form method="POST"
                                              action="{{ route('sessions.close-attendance', $session->session_id) }}"
                                              data-confirm="{{ __('pages.confirm_close_attendance') }}"
                                              onsubmit="return confirm(this.dataset.confirm)">
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
                                          data-confirm="{{ __('pages.confirm_delete_session') }}"
                                          onsubmit="return confirm(this.dataset.confirm)">
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
                                $columnCount = $canManageSessions ? 8 : 5;
                            @endphp
                            <td colspan="{{ $columnCount }}" class="text-center text-muted-theme py-4">
                                {{ __('pages.no_sessions_yet') }}
                                @if($canManageSessions)
                                    <a href="{{ route('sessions.create') }}">{{ __('pages.create_now') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

            <div class="d-lg-none admin-data-cards student-data-hub p-3">
                @forelse($sessions as $session)
                    <article class="data-card app-card card shadow-sm">
                        <div class="card-body">
                            <div class="data-card-title">{{ $session->session_title }}</div>
                            <dl class="data-meta-list mb-3">
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.number') }}</dt>
                                    <dd>{{ $sessions->firstItem() + $loop->index }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.date') }}</dt>
                                    <dd>
                                        <span class="badge bg-light text-dark border">
                                            {{ \Carbon\Carbon::parse($session->session_date)->format('Y-m-d') }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.course') }}</dt>
                                    <dd>{{ $session->course->title ?? '—' }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.module') }}</dt>
                                    <dd>{{ $session->module->title ?? '—' }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.attendance_count') }}</dt>
                                    <dd><span class="badge bg-secondary">{{ $session->attended_count }}</span></dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.attendance_status') }}</dt>
                                    <dd>
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
                                    </dd>
                                </div>
                            </dl>
                            <div class="data-card-actions d-flex flex-wrap gap-2">
                                <a href="{{ route('attendance.all', ['filter_by' => 'session', 'session_id' => $session->session_id]) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-people"></i> {{ __('pages.view_session_roster') }}
                                </a>
                                @if(($missingCounts[$session->session_id] ?? 0) > 0)
                                    <span class="badge bg-warning text-dark align-self-center">
                                        {{ __('pages.missing_records_count', ['count' => $missingCounts[$session->session_id]]) }}
                                    </span>
                                @endif
                                @php
                                    $sessionDate = $session->session_date?->format('Y-m-d');
                                    $canClose = ! $session->isAttendanceClosed()
                                        && $sessionDate
                                        && $sessionDate <= $todayLocal;
                                @endphp
                                @if($canClose)
                                    <form method="POST"
                                          action="{{ route('sessions.close-attendance', $session->session_id) }}"
                                          class="w-100"
                                          data-confirm="{{ __('pages.confirm_close_attendance') }}"
                                          onsubmit="return confirm(this.dataset.confirm)">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                            <i class="bi bi-lock"></i> {{ __('pages.close_attendance') }}
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('sessions.edit', $session->session_id) }}"
                                   class="btn btn-sm btn-outline-theme">
                                    <i class="bi bi-pencil"></i> {{ __('pages.edit') }}
                                </a>
                                <form method="POST" action="{{ route('sessions.destroy', $session->session_id) }}"
                                      class="w-100"
                                      data-confirm="{{ __('pages.confirm_delete_session') }}"
                                      onsubmit="return confirm(this.dataset.confirm)">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                        <i class="bi bi-trash"></i> {{ __('pages.delete') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="text-center text-muted-theme py-4 mb-0">
                        {{ __('pages.no_sessions_yet') }}
                        <a href="{{ route('sessions.create') }}">{{ __('pages.create_now') }}</a>
                    </p>
                @endforelse
            </div>
        </div>
    </div>
    @else
    <div class="student-data-hub">
        @forelse($sessions as $session)
            <article class="data-card app-card card shadow-sm mb-3">
                <div class="card-body">
                    <div class="data-card-title">{{ $session->session_title }}</div>
                    <dl class="data-meta-list mb-0">
                        <div class="data-meta-row">
                            <dt>{{ __('pages.number') }}</dt>
                            <dd>{{ $sessions->firstItem() + $loop->index }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('pages.date') }}</dt>
                            <dd>
                                <span class="badge bg-light text-dark border">
                                    {{ \Carbon\Carbon::parse($session->session_date)->format('Y-m-d') }}
                                </span>
                            </dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('pages.course') }}</dt>
                            <dd>{{ $session->course->title ?? '—' }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('pages.module') }}</dt>
                            <dd>{{ $session->module->title ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </article>
        @empty
            <p class="text-center text-muted-theme py-4">{{ __('pages.no_sessions_yet') }}</p>
        @endforelse
    </div>
    @endif

    @include('partials.pagination', ['paginator' => $sessions])
</div>
@endsection
