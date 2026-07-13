@extends('layouts.app')

@section('title', __('systemtests.dashboard'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0">
            @include('partials.superadmin-entry-tag', ['class' => 'me-2'])
            {{ __('systemtests.dashboard') }}
        </h1>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back_to_superadmin') }}</a>
    </div>

    <p class="text-muted">{{ __('systemtests.intro') }}</p>

    {{-- Run controls: each pipeline runs in isolation; "all" runs them in sequence. --}}
    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 class="h6 mb-0">{{ __('systemtests.run_pipelines') }}</h2>
                <form method="POST" action="{{ route('superadmin.system-tests.run') }}">
                    @csrf
                    <input type="hidden" name="suite" value="all">
                    <button class="btn btn-primary btn-sm">{{ __('systemtests.run_all_sequence') }}</button>
                </form>
            </div>
            <div class="row g-2">
                @foreach($suites as $suite)
                    <div class="col-6 col-md-3">
                        <form method="POST" action="{{ route('superadmin.system-tests.run') }}">
                            @csrf
                            <input type="hidden" name="suite" value="{{ $suite }}">
                            <button class="btn btn-outline-primary w-100 btn-sm">{{ __('systemtests.suite_'.$suite) }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Status board: latest result per pipeline. --}}
    <h2 class="h6 mb-3">{{ __('systemtests.latest_by_pipeline') }}</h2>
    <div class="row g-3 mb-4">
        @foreach($suites as $suite)
            @php($run = $latestBySuite->get($suite))
            <div class="col-6 col-md-3">
                <div class="app-card card shadow-sm h-100 border-{{ $run ? ($run->isPassing() ? 'success' : 'danger') : 'secondary' }}">
                    <div class="card-body py-3">
                        <div class="fw-semibold text-uppercase small">{{ __('systemtests.suite_'.$suite) }}</div>
                        @if($run)
                            <div class="fs-5 fw-bold">{{ $run->passed }}/{{ $run->total }}</div>
                            <div class="small">
                                <span class="badge bg-{{ $run->isPassing() ? 'success' : 'danger' }}">{{ __('systemtests.status_'.$run->status) }}</span>
                                @if($run->skipped > 0)
                                    <span class="badge bg-warning text-dark">{{ __('systemtests.skipped_n', ['n' => $run->skipped]) }}</span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">{{ $run->duration_ms }}ms · {{ $run->created_at?->diffForHumans() }}</div>
                        @else
                            <div class="small text-muted mt-2">{{ __('systemtests.never_run') }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Run history --}}
    <h2 class="h6 mb-3">{{ __('systemtests.history') }}</h2>
    <div class="table-responsive d-none d-lg-block admin-table-desktop app-card card shadow-sm">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('systemtests.pipeline') }}</th>
                    <th>{{ __('pages.status') }}</th>
                    <th>{{ __('systemtests.results') }}</th>
                    <th>{{ __('systemtests.duration') }}</th>
                    <th>{{ __('systemtests.triggered_by') }}</th>
                    <th>{{ __('pages.date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td class="text-uppercase small fw-semibold">{{ __('systemtests.suite_'.$run->suite) }}</td>
                    <td><span class="badge bg-{{ $run->isPassing() ? 'success' : 'danger' }}">{{ __('systemtests.status_'.$run->status) }}</span></td>
                    <td>{{ $run->summary }}</td>
                    <td>{{ $run->duration_ms }}ms</td>
                    <td class="small">{{ $run->triggeredBy?->first_name ?? '—' }}</td>
                    <td class="small">{{ $run->created_at?->format('Y-m-d H:i') }}</td>
                    <td><a href="{{ route('superadmin.system-tests.show', $run->test_run_id) }}" class="btn btn-outline-secondary btn-sm">{{ __('systemtests.view_output') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('systemtests.no_history') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-lg-none admin-data-cards">
        @forelse($runs as $run)
            <article class="data-card app-card card shadow-sm mb-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="data-card-title text-uppercase">{{ $run->suite }}</div>
                        <span class="badge bg-{{ $run->isPassing() ? 'success' : 'danger' }}">{{ __('systemtests.status_'.$run->status) }}</span>
                    </div>
                    <div class="small mt-1">{{ $run->summary }}</div>
                    <div class="small text-muted">{{ $run->duration_ms }}ms · {{ $run->created_at?->format('Y-m-d H:i') }}</div>
                    <a href="{{ route('superadmin.system-tests.show', $run->test_run_id) }}" class="btn btn-outline-secondary btn-sm mt-2">{{ __('systemtests.view_output') }}</a>
                </div>
            </article>
        @empty
            <p class="text-center text-muted py-4">{{ __('systemtests.no_history') }}</p>
        @endforelse
    </div>

    <div class="mt-3">{{ $runs->links() }}</div>
</div>
@endsection
