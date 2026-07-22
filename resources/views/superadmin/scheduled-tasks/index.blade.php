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

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h6 mb-3">{{ __('scheduled_tasks.create_custom') }}</h2>
            <form method="POST" action="{{ route('superadmin.scheduled-tasks.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small" for="custom-slug">{{ __('scheduled_tasks.field_slug') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-slug" name="slug" value="{{ old('slug') }}" placeholder="my-nightly-job" required pattern="[a-z][a-z0-9\-]*">
                        <div class="form-text">{{ __('scheduled_tasks.slug_hint') }}</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="custom-label-en">{{ __('scheduled_tasks.field_label_en') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-label-en" name="label_en" value="{{ old('label_en') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="custom-label-ar">{{ __('scheduled_tasks.field_label_ar') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-label-ar" name="label_ar" value="{{ old('label_ar') }}" required dir="rtl">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="custom-command">{{ __('scheduled_tasks.field_command') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-command" name="command" value="{{ old('command') }}" list="artisan-commands" required>
                        <datalist id="artisan-commands">
                            @foreach($availableCommands as $commandName)
                                <option value="{{ $commandName }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="custom-cron">{{ __('scheduled_tasks.field_cron') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-cron" name="cron_expression" value="{{ old('cron_expression', '0 2 * * *') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="custom-timezone">{{ __('scheduled_tasks.field_timezone') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-timezone" name="timezone" value="{{ old('timezone', config('attendance.timezone', config('app.timezone'))) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="custom-desc-en">{{ __('scheduled_tasks.field_description_en') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-desc-en" name="description_en" value="{{ old('description_en') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small" for="custom-desc-ar">{{ __('scheduled_tasks.field_description_ar') }}</label>
                        <input type="text" class="form-control form-control-sm" id="custom-desc-ar" name="description_ar" value="{{ old('description_ar') }}" dir="rtl">
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="custom-parameters">{{ __('scheduled_tasks.field_parameters_json') }}</label>
                        <input type="text" class="form-control form-control-sm font-monospace" id="custom-parameters" name="parameters_json" value="{{ old('parameters_json') }}" placeholder='{"--date":"2026-07-22"}'>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="custom-enabled" @checked(old('enabled', true))>
                            <label class="form-check-label small" for="custom-enabled">{{ __('scheduled_tasks.enabled') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="run_after_create" value="1" id="custom-run-after" @checked(old('run_after_create', true))>
                            <label class="form-check-label small" for="custom-run-after">{{ __('scheduled_tasks.run_after_create') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> {{ __('scheduled_tasks.create_task') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <h2 class="h6 mb-3">{{ __('scheduled_tasks.registered_tasks') }}</h2>
    <div class="row g-3 mb-4">
        @foreach($tasks as $task)
            @php($lastRun = $task['last_run'] ?? null)
            @php($isCustom = $task['is_custom'] ?? false)
            <div class="col-12">
                <div class="app-card card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h6 mb-0">
                                        @if($isCustom)
                                            {{ $task['label'] }}
                                        @else
                                            {{ __($task['label']) }}
                                        @endif
                                    </h3>
                                    @if($isCustom)
                                        <span class="badge bg-info text-dark">{{ __('scheduled_tasks.custom_badge') }}</span>
                                    @endif
                                    @if(!($task['enabled'] ?? true))
                                        <span class="badge bg-secondary">{{ __('scheduled_tasks.disabled') }}</span>
                                    @elseif(!($task['when_active'] ?? true))
                                        <span class="badge bg-warning text-dark">{{ __('scheduled_tasks.conditional_inactive') }}</span>
                                    @else
                                        <span class="badge bg-success">{{ __('scheduled_tasks.active') }}</span>
                                    @endif
                                </div>
                                <p class="small text-muted-theme mb-2">
                                    @if($isCustom)
                                        {{ $task['description'] }}
                                    @else
                                        {{ __($task['description']) }}
                                    @endif
                                </p>
                                <div class="small text-muted-theme">
                                    @if(!empty($task['command_display']))
                                        <div><code>{{ $task['command_display'] }}</code></div>
                                    @endif
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
                                @if($isCustom)
                                    <form method="POST" action="{{ route('superadmin.scheduled-tasks.destroy', $task['key']) }}" onsubmit="return confirm(@json(__('scheduled_tasks.delete_confirm')));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-trash"></i> {{ __('scheduled_tasks.delete_task') }}
                                        </button>
                                    </form>
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
                                    <label class="form-label small mb-1" for="cron-{{ $task['key'] }}">
                                        {{ $isCustom ? __('scheduled_tasks.field_cron') : __('scheduled_tasks.cron_override') }}
                                    </label>
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           id="cron-{{ $task['key'] }}"
                                           name="cron_expression"
                                           value="{{ $task['cron_expression'] ?? $task['expression'] }}"
                                           placeholder="{{ $task['expression'] ?? '5 0 * * *' }}"
                                           @if($isCustom) required @endif>
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
                    <td class="small fw-semibold">{{ app(\App\Services\ScheduledTaskRegistrar::class)->taskDisplayName($run->task_key) }}</td>
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
