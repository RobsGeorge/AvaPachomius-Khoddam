@extends('layouts.app')

@section('title', __('events.tests_dashboard'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0">{{ __('events.tests_dashboard') }}</h1>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back_to_superadmin') }}</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach(['unit','feature','load','all'] as $suite)
            <div class="col-md-3">
                <form method="POST" action="{{ route('superadmin.events.tests.run') }}">@csrf
                    <input type="hidden" name="suite" value="{{ $suite }}">
                    <button class="btn btn-outline-primary w-100">{{ __('events.run_'.$suite.'_tests') }}</button>
                </form>
            </div>
        @endforeach
    </div>

    @if($latestBySuite->isNotEmpty())
        <h2 class="h6 mb-3">{{ __('events.latest_by_suite') }}</h2>
        <div class="row g-3 mb-4">
            @foreach($latestBySuite as $run)
                <div class="col-md-4">
                    <div class="app-card card shadow-sm border-{{ $run->isPassing() ? 'success' : 'danger' }}">
                        <div class="card-body">
                            <div class="fw-semibold text-uppercase">{{ $run->suite }}</div>
                            <div>{{ $run->passed }}/{{ $run->total }} {{ __('events.tests_passed') }}</div>
                            <div class="small text-muted">{{ $run->duration_ms }}ms · {{ $run->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-sm mb-0">
            <thead><tr><th>{{ __('events.suite') }}</th><th>{{ __('pages.status') }}</th><th>{{ __('events.results') }}</th><th>{{ __('events.duration') }}</th><th>{{ __('pages.date') }}</th></tr></thead>
            <tbody>
            @foreach($runs as $run)
                <tr>
                    <td>{{ $run->suite }}</td>
                    <td><span class="badge bg-{{ $run->isPassing() ? 'success' : 'danger' }}">{{ $run->status }}</span></td>
                    <td>{{ $run->summary }}</td>
                    <td>{{ $run->duration_ms }}ms</td>
                    <td>{{ $run->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $runs->links() }}
</div>
@endsection
