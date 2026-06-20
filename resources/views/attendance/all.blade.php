@extends('layouts.app')

@section('content')
<div class="container py-4 animate-in">
    <div class="app-card card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h1 class="page-title mb-0">{{ __('pages.all_records') }}</h1>
            </div>

            @if(session('success'))
                <div class="alert alert-success mb-3">{{ session('success') }}</div>
            @endif

            <form method="GET" action="{{ route('attendance.all') }}" class="app-card card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-4">
                            <label class="form-label small fw-semibold">{{ __('pages.attendance_group_by') }}</label>
                            <div class="btn-group w-100 flex-wrap" role="group">
                                <input type="radio" class="btn-check" name="group_by" id="group-by-date" value="date"
                                       {{ ($groupBy ?? 'date') === 'date' ? 'checked' : '' }} onchange="this.form.submit()">
                                <label class="btn btn-outline-theme btn-sm" for="group-by-date">{{ __('pages.group_by_date') }}</label>
                                <input type="radio" class="btn-check" name="group_by" id="group-by-session" value="session"
                                       {{ ($groupBy ?? 'date') === 'session' ? 'checked' : '' }} onchange="this.form.submit()">
                                <label class="btn btn-outline-theme btn-sm" for="group-by-session">{{ __('pages.group_by_session') }}</label>
                                <input type="radio" class="btn-check" name="group_by" id="group-by-status" value="status"
                                       {{ ($groupBy ?? 'date') === 'status' ? 'checked' : '' }} onchange="this.form.submit()">
                                <label class="btn btn-outline-theme btn-sm" for="group-by-status">{{ __('pages.group_by_status') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="session_date" class="form-label small fw-semibold">{{ __('pages.date') }}</label>
                            <input type="date" id="session_date" name="session_date" class="form-control form-control-sm"
                                   value="{{ request('session_date') }}">
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label for="session_id" class="form-label small fw-semibold">{{ __('pages.session') }}</label>
                            <select id="session_id" name="session_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.all_sessions') }}</option>
                                @foreach($sessionOptions as $session)
                                    <option value="{{ $session->session_id }}" @selected(request('session_id') == $session->session_id)>
                                        {{ $session->session_date?->format('Y-m-d') }} — {{ $session->session_title }}
                                        @if($session->course)
                                            ({{ $session->course->title }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="status" class="form-label small fw-semibold">{{ __('pages.status') }}</label>
                            <select id="status" name="status" class="form-select form-select-sm">
                                <option value="">{{ __('pages.all_statuses') }}</option>
                                <option value="Present" @selected(request('status') === 'Present')>{{ __('pages.present') }}</option>
                                <option value="Absent" @selected(request('status') === 'Absent')>{{ __('pages.absent') }}</option>
                                <option value="Late" @selected(request('status') === 'Late')>{{ __('pages.late') }}</option>
                                <option value="Permission" @selected(request('status') === 'Permission')>{{ __('pages.permission') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-1 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="{{ __('pages.filter') }}">
                                <i class="bi bi-funnel"></i>
                            </button>
                            @if(request()->hasAny(['session_date', 'session_id', 'status']))
                                <a href="{{ route('attendance.all', ['group_by' => $groupBy ?? 'date']) }}"
                                   class="btn btn-outline-secondary btn-sm" title="{{ __('pages.clear_filters') }}">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            @if(empty($groups))
                <p class="mb-4">{{ __('pages.no_attendance_records') }}.</p>
            @else
                <div class="accordion mb-4" id="attendance-groups">
                    @foreach($groups as $index => $group)
                        @php
                            $collapseId = 'attendance-group-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $group['key']);
                            $isOpen = $index === 0;
                            $stats = $group['stats'];
                            $presentPct = $stats['total'] ? round((($stats['present'] + $stats['permission']) / $stats['total']) * 100) : 0;
                        @endphp
                        <div class="accordion-item app-card border mb-2 overflow-hidden">
                            <h2 class="accordion-header" id="heading-{{ $collapseId }}">
                                <button class="accordion-button {{ $isOpen ? '' : 'collapsed' }} py-3"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#{{ $collapseId }}"
                                        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                                        aria-controls="{{ $collapseId }}">
                                    <div class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                        <span class="fw-semibold">{{ $group['heading'] }}</span>
                                        @if(! empty($group['meta']))
                                            <span class="badge bg-light text-dark border">{{ $group['meta'] }}</span>
                                        @endif
                                        <span class="badge bg-secondary ms-auto">
                                            {{ __('pages.records_in_group', ['count' => $stats['total']]) }}
                                        </span>
                                        <span class="badge bg-success">{{ $presentPct }}%</span>
                                    </div>
                                </button>
                            </h2>
                            <div id="{{ $collapseId }}"
                                 class="accordion-collapse collapse {{ $isOpen ? 'show' : '' }}"
                                 aria-labelledby="heading-{{ $collapseId }}"
                                 data-bs-parent="#attendance-groups">
                                <div class="accordion-body p-0">
                                    <div class="px-3 py-2 border-bottom bg-light small d-flex flex-wrap gap-3">
                                        <span class="text-success">{{ __('pages.present') }}: <strong>{{ $stats['present'] }}</strong></span>
                                        <span class="text-danger">{{ __('pages.absent') }}: <strong>{{ $stats['absent'] }}</strong></span>
                                        <span class="text-warning">{{ __('pages.late') }}: <strong>{{ $stats['late'] }}</strong></span>
                                        @if($stats['permission'] > 0)
                                            <span>{{ __('pages.permission') }}: <strong>{{ $stats['permission'] }}</strong></span>
                                        @endif
                                    </div>
                                    @if($subgroupByStatus ?? false)
                                        @include('attendance.partials.group-records-by-status', [
                                            'records' => $group['records'],
                                            'showSessionColumn' => ($groupBy ?? 'date') === 'date',
                                            'showDateColumn' => ($groupBy ?? 'date') === 'session',
                                        ])
                                    @else
                                        @include('attendance.partials.group-records-table', [
                                            'records' => $group['records'],
                                            'showSessionColumn' => ($groupBy ?? 'date') === 'date' || ($groupBy ?? 'date') === 'status',
                                            'showDateColumn' => ($groupBy ?? 'date') === 'status',
                                            'showStatusColumn' => ($groupBy ?? 'date') !== 'status',
                                        ])
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @include('partials.pagination', ['paginator' => $groupPaginator])
            @endif

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">{{ __('pages.general_stats') }}</h3>
                            <p class="mb-1">{{ __('pages.total_records') }}: <strong>{{ $overallStats->total ?? 0 }}</strong></p>
                            <p class="mb-1 text-success">{{ __('pages.present_plural') }}: <strong>{{ $overallStats->present ?? 0 }}</strong></p>
                            <p class="mb-1 text-danger">{{ __('pages.absent_count') }}: <strong>{{ $overallStats->absent ?? 0 }}</strong></p>
                            <p class="mb-1 text-warning">{{ __('pages.late_count') }}: <strong>{{ $overallStats->late ?? 0 }}</strong></p>
                            <p class="mb-0">{{ __('pages.attendance_rate') }}: <strong>{{ ($overallStats->total ?? 0) ? round((($overallStats->present ?? 0) / $overallStats->total) * 100) : 0 }}%</strong></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">{{ __('pages.daily_stats') }}</h3>
                            @forelse($dailyStats as $stat)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="fw-semibold">{{ $stat->date }}</div>
                                    <div class="small text-muted-theme">{{ __('pages.attendance_label') }} {{ $stat->present }} ({{ $stat->total ? round(($stat->present / $stat->total) * 100) : 0 }}%)</div>
                                </div>
                            @empty
                                <p class="small text-muted-theme mb-0">—</p>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card card h-100">
                        <div class="card-body">
                            <h3 class="h6 fw-semibold mb-3">{{ __('pages.highest_attendance') }}</h3>
                            @forelse($userStats as $stat)
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="fw-semibold">{{ $stat->first_name . ' ' . $stat->second_name }}</div>
                                    <div class="small text-muted-theme">{{ __('pages.attendance_rate') }}: {{ $stat->total ? round(($stat->present / $stat->total) * 100) : 0 }}%</div>
                                </div>
                            @empty
                                <p class="small text-muted-theme mb-0">—</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('attendance.partials.status-scripts')
@endsection
