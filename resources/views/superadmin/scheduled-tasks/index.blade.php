@extends('layouts.app')

@section('title', __('scheduled_tasks.dashboard'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <h1 class="page-title mb-0">
            @include('partials.superadmin-entry-tag', ['class' => 'me-2'])
            {{ __('scheduled_tasks.dashboard') }}
        </h1>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('pages.back_to_superadmin') }}</a>
    </div>

    <p class="text-muted-theme">{{ __('scheduled_tasks.intro') }}</p>

    <div class="app-card card shadow-sm mb-4 border-info border-opacity-25">
        <div class="card-body small">
            <strong>{{ __('scheduled_tasks.cron_notice_title') }}</strong>
            <p class="mb-0 mt-1">{{ __('scheduled_tasks.cron_notice_body') }}</p>
        </div>
    </div>

    <h2 class="h6 mb-3">{{ __('scheduled_tasks.registered_tasks') }}</h2>
    <div class="row g-3 mb-4">
        @foreach($tasks as $task)
            @php($lastRun = $task['last_run'] ?? null)
            <div class="col-12">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h6 mb-0">{{ __($task['label']) }}</h3>
                                    @if(!($task['enabled'] ?? true))
                                        <span class="badge bg-secondary">{{ __('scheduled_tasks.disabled') }}</span>
                                    @elseif(!($task['when_active'] ?? true))
                                        <span class="badge bg-warning text-dark">{{ __('scheduled_tasks.conditional_inactive') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ __('scheduled_tasks.active') }}</span>
                                    @endif
                                </div>
                                <p class="small text-muted-theme mb-2">{{ __($task['description']) }}</p>
                                <div class="small text-muted-theme">
                                    <div><code>{{ $task['expression'] ?? '—' }}</code></div>
                                    @if(!empty($task['next_run_at']))
                                        <div class="mt-1">{{ __('scheduled_tasks.next_run') }}: {{ $task['next_run_at']->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</div>
                                    @endif
                                    @if($lastRun)
                                        <div class="mt-1">
                                            {{ __('scheduled_tasks.last_run') }}:
                                            <span class="badge bg-{{ $lastRun->isSuccess() ? 'success' : ($lastRun->isRunning() ? 'secondary' : 'danger') }}">
                                                {{ __('scheduled_tasks.status_'.$lastRun->status) }}
                                            </span>
                                            · {{ $lastRun->duration_ms }}ms · {{ $lastRun->started_at?->diffForHumans() }}
                                        </div>
                                    @else
                                        <div class="mt-1">{{ __('scheduled_tasks.never_run') }}</div>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2 flex-shrink-0">
                                <form method="POST" action="{{ route('superadmin.scheduled-tasks.run', $task['key']) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-play-fill"></i> {{ __('scheduled_tasks.run_now') }}
                                    </button>
                                </form>
                                @if($lastRun)
                                    <a href="{{ route('superadmin.scheduled-tasks.show', $lastRun) }}" class="btn btn-outline-secondary btn-sm">
                                        {{ __('scheduled_tasks.view_last_output') }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('superadmin.scheduled-tasks.settings', $task['key']) }}" class="border-top pt-3 mt-3">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled-{{ $task['key'] }}" @checked($task['enabled'] ?? true)>
                                        <label class="form-check-label small" for="enabled-{{ $task['key'] }}">{{ __('scheduled_tasks.enabled') }}</label>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small mb-1" for="cron-{{ $task['key'] }}">{{ __('scheduled_tasks.cron_override') }}</label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="cron-{{ $task['key'] }}"
                                           name="cron_expression"
                                           value="{{ $task['cron_expression'] }}"
                                           placeholder="{{ $task['expression'] ?? '5 0 * * *' }}">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('scheduled_tasks.save_settings') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="h6 mb-3">{{ __('scheduled_tasks.history') }}</h2>
    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('scheduled_tasks.task') }}</th>
                    <th>{{ __('pages.status') }}</th>
                    <th>{{ __('scheduled_tasks.trigger') }}</th>
                    <th>{{ __('scheduled_tasks.duration') }}</th>
                    <th>{{ __('scheduled_tasks.triggered_by') }}</th>
                    <th>{{ __('pages.date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td class="small fw-semibold">{{ __('scheduled_tasks.tasks.' . str_replace('.', '_', $run->task_key)) }}</td>
                    <td><span class="badge bg-{{ $run->isSuccess() ? 'success' : ($run->isRunning() ? 'secondary' : 'danger') }}">{{ __('scheduled_tasks.status_'.$run->status) }}</span></td>
                    <td class="small">{{ __('scheduled_tasks.trigger_'.$run->trigger) }}</td>
                    <td>{{ $run->duration_ms }}ms</td>
                    <td class="small">{{ $run->triggeredBy?->displayName() ?? '—' }}</td>
                    <td class="small">{{ $run->started_at?->format('Y-m-d H:i') }}</td>
                    <td><a href="{{ route('superadmin.scheduled-tasks.show', $run) }}" class="btn btn-outline-secondary btn-sm">{{ __('scheduled_tasks.view_output') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('scheduled_tasks.no_history') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $runs->links() }}</div>
</div>
@endsection
