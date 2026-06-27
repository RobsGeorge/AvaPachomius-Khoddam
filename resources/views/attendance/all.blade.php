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

            <form method="GET" action="{{ route('attendance.all') }}" id="attendance-filter-form" class="app-card card mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3">
                            <label class="form-label small fw-semibold">{{ __('pages.filter_by') }}</label>
                            <div class="btn-group w-100 flex-wrap" role="group">
                                <input type="radio" class="btn-check" name="filter_by" id="filter-by-date" value="date"
                                       {{ ($filterBy ?? 'date') === 'date' ? 'checked' : '' }}>
                                <label class="btn btn-outline-theme btn-sm" for="filter-by-date">{{ __('pages.group_by_date') }}</label>
                                <input type="radio" class="btn-check" name="filter_by" id="filter-by-session" value="session"
                                       {{ ($filterBy ?? 'date') === 'session' ? 'checked' : '' }}>
                                <label class="btn btn-outline-theme btn-sm" for="filter-by-session">{{ __('pages.group_by_session') }}</label>
                                <input type="radio" class="btn-check" name="filter_by" id="filter-by-module" value="module"
                                       {{ ($filterBy ?? 'date') === 'module' ? 'checked' : '' }}>
                                <label class="btn btn-outline-theme btn-sm" for="filter-by-module">{{ __('pages.group_by_module') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 {{ ($filterBy ?? 'date') === 'date' ? '' : 'd-none' }}" id="filter-date-wrap">
                            <label for="session_date" class="form-label small fw-semibold">{{ __('pages.date') }}</label>
                            <input type="date" id="session_date" name="session_date" class="form-control form-control-sm"
                                   value="{{ ($filterBy ?? 'date') === 'date' ? request('session_date') : '' }}">
                        </div>
                        <div class="col-md-6 col-lg-3 {{ ($filterBy ?? 'date') === 'session' ? '' : 'd-none' }}" id="filter-session-wrap">
                            <label for="session_id" class="form-label small fw-semibold">{{ __('pages.session') }}</label>
                            <select id="session_id" name="session_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.select_session') }}</option>
                                @foreach($sessionOptions as $session)
                                    <option value="{{ $session->session_id }}" @selected(($filterBy ?? 'date') === 'session' && request('session_id') == $session->session_id)>
                                        {{ $session->session_date?->format('Y-m-d') }} — {{ $session->session_title }}
                                        @if($session->course)
                                            ({{ $session->course->title }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-3 {{ ($filterBy ?? 'date') === 'module' ? '' : 'd-none' }}" id="filter-module-wrap">
                            <label for="module_id" class="form-label small fw-semibold">{{ __('pages.module') }}</label>
                            <select id="module_id" name="module_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.select_module') }}</option>
                                @foreach($moduleOptions as $module)
                                    <option value="{{ $module->module_id }}" @selected(($filterBy ?? 'date') === 'module' && request('module_id') == $module->module_id)>
                                        {{ $module->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-3 {{ ($filterBy ?? 'date') === 'module' ? '' : 'd-none' }}" id="filter-module-session-wrap">
                            <label for="module_session_id" class="form-label small fw-semibold">{{ __('pages.session') }} <span class="text-muted-theme fw-normal">({{ __('pages.optional') }})</span></label>
                            <select id="module_session_id" name="session_id" class="form-select form-select-sm">
                                <option value="">{{ __('pages.all_module_sessions') }}</option>
                                @foreach($moduleSessionOptions as $session)
                                    <option value="{{ $session->session_id }}" @selected(($filterBy ?? 'date') === 'module' && request('session_id') == $session->session_id)>
                                        {{ $session->session_date?->format('Y-m-d') }} — {{ $session->session_title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="bi bi-funnel"></i> {{ __('pages.filter') }}
                            </button>
                            @if(request()->hasAny(['session_date', 'session_id', 'module_id']))
                                <a href="{{ route('attendance.all', ['filter_by' => $filterBy ?? 'date']) }}"
                                   class="btn btn-outline-secondary btn-sm" title="{{ __('pages.clear_filters') }}">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            @if(($filterBy ?? 'date') === 'session' && ! request('session_id'))
                <p class="mb-4 text-muted-theme">{{ __('pages.select_session_to_view') }}</p>
            @elseif(($filterBy ?? 'date') === 'module' && ! request('module_id'))
                <p class="mb-4 text-muted-theme">{{ __('pages.select_module_to_view') }}</p>
            @elseif(empty($groups))
                <p class="mb-4">{{ __('pages.no_attendance_records') }}.</p>
            @elseif($singleSessionReport ?? false)
                @php $group = $groups[0]; $stats = $group['stats']; @endphp
                <div class="app-card card border mb-4 overflow-hidden">
                    <div class="card-header py-3">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">{{ $group['heading'] }}</span>
                            @if(! empty($group['meta']))
                                <span class="badge bg-light text-dark border">{{ $group['meta'] }}</span>
                            @endif
                            @if($group['session']?->isAttendanceClosed())
                                <span class="badge bg-secondary">{{ __('pages.attendance_status_closed') }}</span>
                            @endif
                            <span class="badge bg-secondary ms-auto">
                                {{ __('pages.records_in_group', ['count' => $stats['total']]) }}
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @if(! empty($group['roster']))
                            <div class="px-3 py-2 border-bottom bg-light small d-flex flex-wrap gap-3">
                                <span class="text-success">{{ __('pages.present') }}: <strong>{{ $stats['present'] }}</strong></span>
                                <span class="text-danger">{{ __('pages.absent') }}: <strong>{{ $stats['absent'] }}</strong></span>
                                <span class="text-warning">{{ __('pages.late') }}: <strong>{{ $stats['late'] }}</strong></span>
                                @if($stats['permission'] > 0)
                                    <span>{{ __('pages.permission') }}: <strong>{{ $stats['permission'] }}</strong></span>
                                @endif
                            </div>
                        @else
                            <div class="px-3 py-2 border-bottom bg-light small d-flex flex-wrap gap-3">
                                <span class="text-success">{{ __('pages.present') }}: <strong>{{ $stats['present'] }}</strong></span>
                                <span class="text-danger">{{ __('pages.absent') }}: <strong>{{ $stats['absent'] }}</strong></span>
                                <span class="text-warning">{{ __('pages.late') }}: <strong>{{ $stats['late'] }}</strong></span>
                                @if($stats['permission'] > 0)
                                    <span>{{ __('pages.permission') }}: <strong>{{ $stats['permission'] }}</strong></span>
                                @endif
                            </div>
                        @endif
                        @include('attendance.partials.session-roster-panel', ['group' => $group])
                    </div>
                </div>
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
                                    @include('attendance.partials.group-records-by-status', [
                                        'records' => $group['records'],
                                        'showSessionColumn' => ($filterBy ?? 'date') === 'date' && ! request('session_date'),
                                        'showDateColumn' => false,
                                    ])
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

@push('scripts')
<script>
(function () {
    const form = document.getElementById('attendance-filter-form');
    if (!form) return;

    const dateWrap = document.getElementById('filter-date-wrap');
    const sessionWrap = document.getElementById('filter-session-wrap');
    const moduleWrap = document.getElementById('filter-module-wrap');
    const moduleSessionWrap = document.getElementById('filter-module-session-wrap');
    const dateInput = document.getElementById('session_date');
    const sessionSelect = document.getElementById('session_id');
    const moduleSelect = document.getElementById('module_id');
    const moduleSessionSelect = document.getElementById('module_session_id');

    function syncFilterFields() {
        const filterBy = form.querySelector('[name=filter_by]:checked')?.value ?? 'date';

        dateWrap?.classList.toggle('d-none', filterBy !== 'date');
        sessionWrap?.classList.toggle('d-none', filterBy !== 'session');
        moduleWrap?.classList.toggle('d-none', filterBy !== 'module');
        moduleSessionWrap?.classList.toggle('d-none', filterBy !== 'module');

        if (dateInput) {
            dateInput.disabled = filterBy !== 'date';
            if (filterBy !== 'date') dateInput.value = '';
        }
        if (sessionSelect) {
            sessionSelect.disabled = filterBy !== 'session';
            sessionSelect.name = filterBy === 'session' ? 'session_id' : '';
            if (filterBy !== 'session') sessionSelect.value = '';
        }
        if (moduleSelect) {
            moduleSelect.disabled = filterBy !== 'module';
            if (filterBy !== 'module') moduleSelect.value = '';
        }
        if (moduleSessionSelect) {
            moduleSessionSelect.disabled = filterBy !== 'module';
            moduleSessionSelect.name = filterBy === 'module' ? 'session_id' : '';
            if (filterBy !== 'module') moduleSessionSelect.value = '';
        }
    }

    moduleSelect?.addEventListener('change', function () {
        if (moduleSessionSelect) moduleSessionSelect.value = '';
        form.submit();
    });

    form.querySelectorAll('[name=filter_by]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            syncFilterFields();
            form.submit();
        });
    });

    syncFilterFields();
})();
</script>
@endpush
@endsection
