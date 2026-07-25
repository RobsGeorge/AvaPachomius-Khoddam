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

    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted-theme">{{ __('scheduled_tasks.stat_total_tasks') }}</div>
                    <div class="fs-4 fw-bold">{{ $stats['total_tasks'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted-theme">{{ __('scheduled_tasks.stat_enabled_tasks') }}</div>
                    <div class="fs-4 fw-bold">{{ $stats['enabled_tasks'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted-theme">{{ __('scheduled_tasks.stat_custom_tasks') }}</div>
                    <div class="fs-4 fw-bold">{{ $stats['custom_tasks'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted-theme">{{ __('scheduled_tasks.stat_runs_24h') }}</div>
                    <div class="fs-4 fw-bold">{{ $stats['runs_last_24h'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="app-card card shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="small text-muted-theme">{{ __('scheduled_tasks.stat_success_rate') }}</div>
                    <div class="fs-4 fw-bold">
                        @if($stats['success_rate'] !== null)
                            {{ $stats['success_rate'] }}%
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="accordion mb-4" id="scheduledTasksAccordion">
        <div class="accordion-item app-card card shadow-sm mb-2 border-0">
            <h2 class="accordion-header">
                <button class="accordion-button {{ ($expand ?? '') === 'execution-report' ? '' : 'collapsed' }} py-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#execution-report"
                        aria-expanded="{{ ($expand ?? '') === 'execution-report' ? 'true' : 'false' }}">
                    {{ __('scheduled_tasks.execution_report') }}
                </button>
            </h2>
            <div id="execution-report"
                 class="accordion-collapse collapse {{ ($expand ?? '') === 'execution-report' ? 'show' : '' }}"
                 data-bs-parent="#scheduledTasksAccordion">
                <div class="accordion-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>{{ __('scheduled_tasks.task') }}</th>
                                    <th>{{ __('pages.status') }}</th>
                                    <th>{{ __('scheduled_tasks.trigger') }}</th>
                                    <th>{{ __('scheduled_tasks.duration') }}</th>
                                    <th>{{ __('scheduled_tasks.impact') }}</th>
                                    <th>{{ __('scheduled_tasks.triggered_by') }}</th>
                                    <th>{{ __('pages.date') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($runsWithImpact as $row)
                                <tr>
                                    <td class="small fw-semibold">{{ $row['task_name'] }}</td>
                                    <td>
                                        <span class="badge bg-{{ $row['run']->isSuccess() ? 'success' : ($row['run']->isRunning() ? 'secondary' : 'danger') }}">
                                            {{ __('scheduled_tasks.status_'.$row['run']->status) }}
                                        </span>
                                    </td>
                                    <td class="small">{{ __('scheduled_tasks.trigger_'.$row['run']->trigger) }}</td>
                                    <td>{{ $row['run']->duration_ms }}ms</td>
                                    <td class="small">{{ $row['impact_summary'] ?? '—' }}</td>
                                    <td class="small">{{ $row['run']->triggeredBy?->displayName() ?? '—' }}</td>
                                    <td class="small">{{ $row['run']->started_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <a href="{{ route('superadmin.scheduled-tasks.show', $row['run']) }}" class="btn btn-outline-secondary btn-sm">
                                            {{ __('scheduled_tasks.view_output') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">{{ __('scheduled_tasks.no_history') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $runs->links() }}</div>
                </div>
            </div>
        </div>

        <div class="accordion-item app-card card shadow-sm mb-2 border-0">
            <h2 class="accordion-header">
                <button class="accordion-button {{ ($expand ?? '') === 'create-task' ? '' : 'collapsed' }} py-2"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#create-task"
                        aria-expanded="{{ ($expand ?? '') === 'create-task' ? 'true' : 'false' }}">
                    {{ __('scheduled_tasks.create_custom') }}
                </button>
            </h2>
            <div id="create-task"
                 class="accordion-collapse collapse {{ ($expand ?? '') === 'create-task' ? 'show' : '' }}"
                 data-bs-parent="#scheduledTasksAccordion">
                <div class="accordion-body">
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
                            <div class="col-md-6">
                                <label class="form-label small" for="custom-timezone">{{ __('scheduled_tasks.field_timezone') }}</label>
                                <input type="text" class="form-control form-control-sm" id="custom-timezone" name="timezone" value="{{ old('timezone', config('attendance.timezone', config('app.timezone'))) }}">
                            </div>
                            <div class="col-12">
                                @include('superadmin.scheduled-tasks.partials.schedule-picker', [
                                    'prefix' => 'schedule',
                                    'idPrefix' => 'create',
                                    'scheduleUi' => [
                                        'frequency' => old('schedule_frequency', 'daily_at'),
                                        'time' => old('schedule_time', '02:00'),
                                        'day' => old('schedule_day', 1),
                                    ],
                                ])
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
        </div>
    </div>

    <h2 class="h6 mb-3">{{ __('scheduled_tasks.registered_tasks') }}</h2>
    <div class="accordion mb-4" id="scheduledTasksListAccordion">
        @foreach($tasks as $task)
            @php
                $lastRun = $task['last_run'] ?? null;
                $isCustom = $task['is_custom'] ?? false;
                $taskExpand = $reportService->taskExpandKey($task['key']);
                $isOpen = ($expand ?? '') === $taskExpand;
                $taskTitle = $isCustom ? $task['label'] : __($task['label']);
            @endphp
            <div class="accordion-item app-card card shadow-sm mb-2 border-0">
                <h2 class="accordion-header">
                    <button class="accordion-button {{ $isOpen ? '' : 'collapsed' }} py-2"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $taskExpand }}"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
                        <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                            <span class="fw-semibold">{{ $taskTitle }}</span>
                            <span class="small text-muted-theme">{{ $task['schedule_label'] }}</span>
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
                            @if($lastRun)
                                <span class="badge bg-{{ $lastRun->isSuccess() ? 'success' : ($lastRun->isRunning() ? 'secondary' : 'danger') }} ms-auto">
                                    {{ __('scheduled_tasks.status_'.$lastRun->status) }}
                                </span>
                            @endif
                        </span>
                    </button>
                </h2>
                <div id="{{ $taskExpand }}"
                     class="accordion-collapse collapse {{ $isOpen ? 'show' : '' }}"
                     data-bs-parent="#scheduledTasksListAccordion">
                    <div class="accordion-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                            <div class="flex-grow-1 min-w-0">
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
                                    @if(!empty($task['next_run_at']))
                                        <div class="mt-1">{{ __('scheduled_tasks.next_run') }}: {{ $task['next_run_at']->timezone(config('app.timezone'))->format('Y-m-d H:i:s T') }}</div>
                                    @endif
                                    @if($lastRun)
                                        <div class="mt-1">
                                            {{ __('scheduled_tasks.last_run') }}:
                                            {{ $lastRun->duration_ms }}ms · {{ $lastRun->started_at?->diffForHumans() }}
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
                                    <form method="POST" action="{{ route('superadmin.scheduled-tasks.destroy', $task['key']) }}" data-confirm="{{ __('scheduled_tasks.delete_confirm') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-trash"></i> {{ __('scheduled_tasks.delete_task') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        @if(!empty($task['recent_runs']) && $task['recent_runs']->isNotEmpty())
                            <h3 class="h6 mb-2">{{ __('scheduled_tasks.recent_runs') }}</h3>
                            <ul class="list-group list-group-flush mb-3">
                                @foreach($task['recent_runs'] as $recentRun)
                                    <li class="list-group-item px-0 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <span class="small">
                                            <span class="badge bg-{{ $recentRun->isSuccess() ? 'success' : ($recentRun->isRunning() ? 'secondary' : 'danger') }}">
                                                {{ __('scheduled_tasks.status_'.$recentRun->status) }}
                                            </span>
                                            · {{ $recentRun->started_at?->format('Y-m-d H:i') }}
                                            · {{ $recentRun->duration_ms }}ms
                                        </span>
                                        <a href="{{ route('superadmin.scheduled-tasks.show', $recentRun) }}" class="btn btn-outline-secondary btn-sm">
                                            {{ __('scheduled_tasks.view_output') }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if($isCustom)
                            <form method="POST" action="{{ route('superadmin.scheduled-tasks.update', $task['key']) }}" class="border-top pt-3">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small" for="label-en-{{ $taskExpand }}">{{ __('scheduled_tasks.field_label_en') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="label-en-{{ $taskExpand }}" name="label_en" value="{{ old('label_en', $task['label_en'] ?? '') }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small" for="label-ar-{{ $taskExpand }}">{{ __('scheduled_tasks.field_label_ar') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="label-ar-{{ $taskExpand }}" name="label_ar" value="{{ old('label_ar', $task['label_ar'] ?? '') }}" required dir="rtl">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small" for="command-{{ $taskExpand }}">{{ __('scheduled_tasks.field_command') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="command-{{ $taskExpand }}" name="command" value="{{ old('command', $task['command_display'] ?? '') }}" list="artisan-commands" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small" for="timezone-{{ $taskExpand }}">{{ __('scheduled_tasks.field_timezone') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="timezone-{{ $taskExpand }}" name="timezone" value="{{ old('timezone', $task['timezone'] ?? config('app.timezone')) }}">
                                    </div>
                                    <div class="col-12">
                                        @include('superadmin.scheduled-tasks.partials.schedule-picker', [
                                            'prefix' => 'schedule',
                                            'idPrefix' => $taskExpand,
                                            'scheduleUi' => $task['schedule_ui'] ?? [],
                                        ])
                                    </div>
                                    <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled-{{ $taskExpand }}" @checked($task['enabled'] ?? true)>
                                            <label class="form-check-label small" for="enabled-{{ $taskExpand }}">{{ __('scheduled_tasks.enabled') }}</label>
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('scheduled_tasks.save_task') }}</button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <form method="POST" action="{{ route('superadmin.scheduled-tasks.settings', $task['key']) }}" class="border-top pt-3">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-12">
                                        @include('superadmin.scheduled-tasks.partials.schedule-picker', [
                                            'prefix' => 'schedule',
                                            'idPrefix' => $taskExpand,
                                            'scheduleUi' => $task['schedule_ui'] ?? [],
                                        ])
                                    </div>
                                    <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled-{{ $taskExpand }}" @checked($task['enabled'] ?? true)>
                                            <label class="form-check-label small" for="enabled-{{ $taskExpand }}">{{ __('scheduled_tasks.enabled') }}</label>
                                        </div>
                                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('scheduled_tasks.save_settings') }}</button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
