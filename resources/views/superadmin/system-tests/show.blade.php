@extends('layouts.app')

@section('title', __('systemtests.output_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0">
            {{ __('systemtests.suite_'.$run->suite) }} · <span class="badge bg-{{ $run->isPassing() ? 'success' : 'danger' }}">{{ __('systemtests.status_'.$run->status) }}</span>
        </h1>
        <a href="{{ route('superadmin.system-tests.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('systemtests.back_to_report') }}</a>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-4 col-md-2">{{ __('systemtests.results') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->summary }}</dd>
                <dt class="col-4 col-md-2">{{ __('systemtests.duration') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->duration_ms }}ms</dd>
                <dt class="col-4 col-md-2">{{ __('systemtests.triggered_by') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->triggeredBy?->first_name ?? '—' }}</dd>
                <dt class="col-4 col-md-2">{{ __('pages.date') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->created_at?->format('Y-m-d H:i:s') }}</dd>
            </dl>
        </div>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <h2 class="h6">{{ __('systemtests.raw_output') }}</h2>
            <pre class="bg-dark text-light p-3 rounded" style="max-height:60vh;overflow:auto;white-space:pre-wrap;word-break:break-word;">{{ $run->output }}</pre>
        </div>
    </div>
</div>
@endsection
