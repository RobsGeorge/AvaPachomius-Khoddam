@extends('layouts.app')

@section('title', __('scheduled_tasks.output_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0">
            {{ $taskName }}
            · <span class="badge bg-{{ $run->isSuccess() ? 'success' : ($run->isRunning() ? 'secondary' : 'danger') }}">{{ __('scheduled_tasks.status_'.$run->status) }}</span>
        </h1>
        <a href="{{ route('superadmin.scheduled-tasks.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('scheduled_tasks.back_to_dashboard') }}</a>
    </div>

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-4 col-md-2">{{ __('scheduled_tasks.trigger') }}</dt>
                <dd class="col-8 col-md-10">{{ __('scheduled_tasks.trigger_'.$run->trigger) }}</dd>
                <dt class="col-4 col-md-2">{{ __('scheduled_tasks.duration') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->duration_ms }}ms</dd>
                <dt class="col-4 col-md-2">{{ __('scheduled_tasks.exit_code') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->exit_code ?? '—' }}</dd>
                <dt class="col-4 col-md-2">{{ __('scheduled_tasks.triggered_by') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->triggeredBy?->displayName() ?? '—' }}</dd>
                <dt class="col-4 col-md-2">{{ __('scheduled_tasks.started_at') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->started_at?->format('Y-m-d H:i:s') }}</dd>
                <dt class="col-4 col-md-2">{{ __('scheduled_tasks.finished_at') }}</dt>
                <dd class="col-8 col-md-10">{{ $run->finished_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
            </dl>
        </div>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <h2 class="h6">{{ __('scheduled_tasks.raw_output') }}</h2>
            <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:60vh;overflow:auto;white-space:pre-wrap;word-break:break-word;">{{ $run->output ?: __('scheduled_tasks.no_output') }}</pre>
        </div>
    </div>
</div>
@endsection
