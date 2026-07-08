@extends('layouts.app')

@section('title', __('pages.audit_report_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-danger fs-6 px-3 py-2">
                <i class="bi bi-shield-lock-fill"></i> {{ __('pages.superadmin_role') }}
            </span>
            <h1 class="page-title mb-0">{{ __('pages.audit_report_title') }}</h1>
        </div>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
        </a>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill"></i>
        {{ __('pages.audit_report_warning') }}
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'activity' ? 'active' : '' }}"
               href="{{ route('superadmin.audit.index', array_merge(request()->except('tab', 'activity_page', 'login_page'), ['tab' => 'activity'])) }}">
                {{ __('pages.activity_log_tab') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'login' ? 'active' : '' }}"
               href="{{ route('superadmin.audit.index', array_merge(request()->except('tab', 'activity_page', 'login_page'), ['tab' => 'login'])) }}">
                {{ __('pages.login_trials_tab') }}
            </a>
        </li>
    </ul>

    @if($tab === 'activity')
        <div class="mb-3">
            <a href="{{ route('superadmin.audit.index', ['tab' => 'activity', 'module' => 'events']) }}"
               class="btn btn-sm {{ request('module') === 'events' ? 'btn-primary' : 'btn-outline-primary' }}">
                {{ __('nav.events') }}
            </a>
            @if(request('module') === 'events')
                <a href="{{ route('superadmin.audit.index', ['tab' => 'activity']) }}" class="btn btn-sm btn-outline-secondary ms-1">
                    {{ __('pages.all') }}
                </a>
            @endif
        </div>
    @endif

    @if($tab === 'login')
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="login">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('pages.email') }}</label>
                        <input type="text" name="email" class="form-control" value="{{ request('email') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pages.context') }}</label>
                        <select name="context" class="form-select">
                            <option value="">{{ __('pages.all') }}</option>
                            <option value="login" @selected(request('context') === 'login')>{{ __('pages.context_login') }}</option>
                            <option value="password_reset" @selected(request('context') === 'password_reset')>{{ __('pages.context_password_reset') }}</option>
                            <option value="set_password" @selected(request('context') === 'set_password')>{{ __('pages.context_set_password') }}</option>
                            <option value="form_password" @selected(request('context') === 'form_password')>{{ __('pages.context_form_password') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pages.result') }}</label>
                        <select name="success" class="form-select">
                            <option value="">{{ __('pages.all') }}</option>
                            <option value="1" @selected(request('success') === '1')>{{ __('pages.trial_success') }}</option>
                            <option value="0" @selected(request('success') === '0')>{{ __('pages.trial_failed') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">{{ __('pages.filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="app-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive d-none d-lg-block admin-table-desktop">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.date_time') }}</th>
                                <th>{{ __('pages.email') }}</th>
                                <th>{{ __('pages.password_attempt') }}</th>
                                <th>{{ __('pages.current_password') }}</th>
                                <th>{{ __('pages.password_confirmation') }}</th>
                                <th>{{ __('pages.context') }}</th>
                                <th>{{ __('pages.route') }}</th>
                                <th>{{ __('pages.result') }}</th>
                                <th>{{ __('pages.user') }}</th>
                                <th>{{ __('pages.ip_address') }}</th>
                                <th>{{ __('pages.device') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loginTrials as $trial)
                                <tr>
                                    <td class="text-nowrap">{{ $trial->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $trial->email ?? '—' }}</td>
                                    <td><code class="text-danger">{{ $trial->password_attempt }}</code></td>
                                    <td>
                                        @if($trial->current_password)
                                            <code>{{ $trial->current_password }}</code>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if($trial->password_confirmation)
                                            <code>{{ $trial->password_confirmation }}</code>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ __('pages.context_'.$trial->context) !== 'pages.context_'.$trial->context ? __('pages.context_'.$trial->context) : $trial->context }}</td>
                                    <td>{{ $trial->route_name ?? '—' }}</td>
                                    <td>
                                        @if($trial->success)
                                            <span class="badge bg-success">{{ __('pages.trial_success') }}</span>
                                        @else
                                            <span class="badge bg-danger">{{ __('pages.trial_failed') }}</span>
                                            @if($trial->failure_reason)
                                                <div class="text-muted mt-1">{{ $trial->failure_reason }}</div>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if($trial->user)
                                            {{ $trial->user->first_name }} {{ $trial->user->second_name }}
                                            <div class="text-muted">#{{ $trial->user_id }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $trial->ip_address ?? '—' }}</td>
                                    <td>{{ $trial->device_summary ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">{{ __('pages.no_login_trials') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-lg-none admin-data-cards student-data-hub p-3">
                    @forelse($loginTrials as $trial)
                        <article class="data-card">
                            <div class="data-card-title">{{ $trial->email ?? '—' }}</div>
                            <dl class="data-meta-list mb-0">
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.date_time') }}</dt>
                                    <dd>{{ $trial->created_at?->format('Y-m-d H:i:s') }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.context') }}</dt>
                                    <dd>{{ __('pages.context_'.$trial->context) !== 'pages.context_'.$trial->context ? __('pages.context_'.$trial->context) : $trial->context }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.result') }}</dt>
                                    <dd>
                                        @if($trial->success)
                                            <span class="badge bg-success">{{ __('pages.trial_success') }}</span>
                                        @else
                                            <span class="badge bg-danger">{{ __('pages.trial_failed') }}</span>
                                        @endif
                                    </dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.ip_address') }}</dt>
                                    <dd>{{ $trial->ip_address ?? '—' }}</dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <p class="text-center py-4 text-muted mb-0">{{ __('pages.no_login_trials') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        @include('partials.pagination', ['paginator' => $loginTrials])
    @else
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="tab" value="activity">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pages.search') }}</label>
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="{{ __('pages.audit_search_placeholder') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('pages.http_method') }}</label>
                        <select name="method" class="form-select">
                            <option value="">{{ __('pages.all') }}</option>
                            @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                                <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('pages.user_id') }}</label>
                        <input type="number" name="user_id" class="form-control" value="{{ request('user_id') }}" min="1">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('pages.module') }}</label>
                        <select name="module" class="form-select">
                            <option value="">{{ __('pages.all') }}</option>
                            <option value="events" @selected(request('module') === 'events')>{{ __('nav.events') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">{{ __('pages.filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="app-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive d-none d-lg-block admin-table-desktop">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('pages.date_time') }}</th>
                                <th>{{ __('pages.user') }}</th>
                                <th>{{ __('pages.http_method') }}</th>
                                <th>{{ __('pages.route') }}</th>
                                <th>{{ __('pages.request_url') }}</th>
                                <th>{{ __('pages.inputs') }}</th>
                                <th>{{ __('pages.status') }}</th>
                                <th>{{ __('pages.ip_address') }}</th>
                                <th>{{ __('pages.device') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activityLogs as $log)
                                <tr>
                                    <td class="text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td>
                                        @if($log->user)
                                            {{ $log->user->first_name }} {{ $log->user->second_name }}
                                            <div class="text-muted">#{{ $log->user_id }}</div>
                                        @else
                                            <span class="text-muted">{{ __('pages.guest') }}</span>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-secondary">{{ $log->http_method }}</span></td>
                                    <td>{{ $log->route_name ?? '—' }}</td>
                                    <td style="max-width:220px; word-break:break-all;">{{ $log->url }}</td>
                                    <td style="max-width:320px;">
                                        @if($log->request_input)
                                            <pre class="mb-0 small bg-light p-2 rounded" style="white-space:pre-wrap;">{{ json_encode($log->request_input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $log->response_status ?? '—' }}</td>
                                    <td>{{ $log->ip_address ?? '—' }}</td>
                                    <td>{{ $log->device_summary ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">{{ __('pages.no_activity_logs') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-lg-none admin-data-cards student-data-hub p-3">
                    @forelse($activityLogs as $log)
                        <article class="data-card">
                            <div class="data-card-title">
                                @if($log->user)
                                    {{ $log->user->first_name }} {{ $log->user->second_name }}
                                @else
                                    {{ __('pages.guest') }}
                                @endif
                            </div>
                            <dl class="data-meta-list mb-0">
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.date_time') }}</dt>
                                    <dd>{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.http_method') }}</dt>
                                    <dd><span class="badge bg-secondary">{{ $log->http_method }}</span></dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.route') }}</dt>
                                    <dd>{{ $log->route_name ?? '—' }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.status') }}</dt>
                                    <dd>{{ $log->response_status ?? '—' }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.ip_address') }}</dt>
                                    <dd>{{ $log->ip_address ?? '—' }}</dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <p class="text-center py-4 text-muted mb-0">{{ __('pages.no_activity_logs') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        @include('partials.pagination', ['paginator' => $activityLogs])
    @endif
</div>
@endsection
