@extends('layouts.app')
@section('title', __('church_cycle.title'))
@section('content')
<div class="container py-4" style="max-width:1100px;">
    <div class="mb-3">
        <h1 class="page-title mb-1">{{ __('church_cycle.title') }}</h1>
        <p class="text-muted-theme mb-0">{{ __('church_cycle.intro') }}</p>
        <p class="small text-muted-theme mt-1 mb-0">{{ __('church_cycle.no_global_upgrade') }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">{{ __('church_cycle.current_year') }}</h2>
            @if($year)
                <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                    <div>
                        <div class="fw-semibold fs-5">{{ $year->label }}</div>
                        <div class="text-muted-theme small">
                            {{ $year->starts_on?->format('Y-m-d') }} → {{ $year->ends_on?->format('Y-m-d') }}
                            · {{ __('church_cycle.status_'.$year->status) }}
                        </div>
                        @if($year->promotion_started_at)
                            <div class="text-muted-theme small">{{ __('church_cycle.start_promotion') }}: {{ $year->promotion_started_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</div>
                        @endif
                    </div>
                    @if($canManage && $year->status !== \App\Support\Structure\SchoolYearStatus::CLOSED)
                        <div class="d-flex flex-wrap gap-2">
                            @if(\App\Support\Structure\SchoolYearStatus::canStartPromotion($year->status))
                                <form method="post" action="{{ route('church.cycle.years.start-promotion', $year) }}" onsubmit="return confirm(@json(__('church_cycle.start_promotion_confirm')))">
                                    @csrf
                                    <button type="submit" class="btn btn-primary">{{ __('church_cycle.start_promotion') }}</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('church.cycle.years.close', $year) }}" onsubmit="return confirm(@json(__('church_cycle.close_year_confirm')))">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary">{{ __('church_cycle.close_year') }}</button>
                            </form>
                            <form method="post" action="{{ route('church.cycle.years.close', $year) }}" onsubmit="return confirm(@json(__('church_cycle.close_year_confirm')))">
                                @csrf
                                <input type="hidden" name="force" value="1">
                                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('church_cycle.force_close') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-3 mt-3 small">
                    <span>{{ __('church_cycle.summary_ready') }}: <strong>{{ $summary['ready'] }}</strong></span>
                    <span>{{ __('church_cycle.summary_blocked') }}: <strong>{{ $summary['blocked'] }}</strong></span>
                    <span>{{ __('church_cycle.summary_done') }}: <strong>{{ $summary['done'] }}</strong></span>
                    <span>{{ __('church_cycle.summary_skipped') }}: <strong>{{ $summary['skipped'] }}</strong></span>
                    <span>{{ __('church_cycle.summary_course_close') }}: <strong>{{ $summary['course_close'] }}</strong></span>
                </div>
            @else
                <p class="text-muted-theme mb-0">{{ __('church_cycle.no_year') }}</p>
            @endif
        </div>
    </div>

    @if($canManage && (! $year || $year->status === \App\Support\Structure\SchoolYearStatus::CLOSED))
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">{{ __('church_cycle.create_year') }}</h2>
                <form method="post" action="{{ route('church.cycle.years.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">{{ __('church_cycle.label') }}</label>
                        <input type="text" name="label" class="form-control" value="{{ old('label') }}" placeholder="{{ __('church_cycle.label_placeholder') }}" required maxlength="64">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('church_cycle.starts_on') }}</label>
                        <input type="date" name="starts_on" class="form-control" value="{{ old('starts_on') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('church_cycle.ends_on') }}</label>
                        <input type="date" name="ends_on" class="form-control" value="{{ old('ends_on') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('church_cycle.status') }}</label>
                        <select name="status" class="form-select">
                            <option value="active" @selected(old('status', 'active') === 'active')>{{ __('church_cycle.status_active') }}</option>
                            <option value="planned" @selected(old('status') === 'planned')>{{ __('church_cycle.status_planned') }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">{{ __('church_cycle.create_year') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="px-3 pt-3">
                <h2 class="h5">{{ __('church_cycle.services') }}</h2>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('church_cycle.services') }}</th>
                            <th>{{ __('church_cycle.policy') }}</th>
                            <th>{{ __('church_cycle.status') }}</th>
                            <th>{{ __('church_cycle.eligible') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['title'] }}</td>
                                <td><span class="small">{{ __('service.progression_'.$row['policy']) }}</span></td>
                                <td>
                                    <span class="badge text-bg-{{ $row['status'] === 'ready' ? 'success' : ($row['status'] === 'blocked' ? 'warning' : ($row['status'] === 'done' ? 'primary' : 'secondary')) }}">
                                        {{ __('church_cycle.row_status_'.$row['status']) }}
                                    </span>
                                    @if(!empty($row['block_reason']))
                                        <div class="small text-muted-theme mt-1">{{ __('church_cycle.block_'.$row['block_reason']) }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if(is_array($row['counts'] ?? null))
                                        {{ $row['counts']['eligible'] ?? 0 }}
                                        ({{ __('church_cycle.ready_count') }}: {{ $row['counts']['ready'] ?? 0 }},
                                        {{ __('church_cycle.blocked_count') }}: {{ $row['counts']['blocked'] ?? 0 }})
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if(in_array($row['status'], ['ready', 'blocked', 'done'], true))
                                        <a href="{{ $row['wizard_url'] }}" class="btn btn-sm btn-outline-primary">{{ __('church_cycle.open_wizard') }}</a>
                                    @endif
                                    @if($canManage && $year && $year->status === \App\Support\Structure\SchoolYearStatus::CLOSING && $row['status'] === 'ready')
                                        <form method="post" action="{{ route('church.cycle.years.services.done', [$year, $row['service_id']]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('church_cycle.mark_done') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-theme py-4">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($history->isNotEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">{{ __('church_cycle.history') }}</h2>
                <ul class="mb-0">
                    @foreach($history as $item)
                        <li>{{ $item->label }} — {{ __('church_cycle.status_'.$item->status) }} ({{ $item->starts_on?->format('Y-m-d') }} → {{ $item->ends_on?->format('Y-m-d') }})</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
@endsection
