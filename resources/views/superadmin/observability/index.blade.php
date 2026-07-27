@extends('layouts.app')

@section('title', __('pages.observability_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            @include('partials.superadmin-entry-tag', ['class' => 'fs-5'])
            <h1 class="page-title mb-0">{{ __('pages.observability_title') }}</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('superadmin.observability.export', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-download"></i> {{ __('pages.observability_export') }}
            </a>
            <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'incidents' ? 'active' : '' }}"
               href="{{ route('superadmin.observability.index', array_merge(request()->except('tab', 'page'), ['tab' => 'incidents'])) }}">
                {{ __('pages.observability_tab_incidents') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'auth' ? 'active' : '' }}"
               href="{{ route('superadmin.observability.index', array_merge(request()->except('tab', 'page'), ['tab' => 'auth'])) }}">
                {{ __('pages.observability_tab_auth') }}
            </a>
        </li>
        @if(!empty($showUsage))
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'usage' ? 'active' : '' }}"
               href="{{ route('superadmin.observability.index', array_merge(request()->except('tab', 'page'), ['tab' => 'usage'])) }}">
                {{ __('pages.observability_tab_usage') }}
            </a>
        </li>
        @endif
        @if(!empty($showLoad))
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'load' ? 'active' : '' }}"
               href="{{ route('superadmin.observability.index', array_merge(request()->except('tab', 'page'), ['tab' => 'load'])) }}">
                {{ __('pages.observability_tab_load') }}
            </a>
        </li>
        @endif
    </ul>

    <form method="get" class="row g-2 mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="col-md-3">
            <label class="form-label">{{ __('pages.observability_filter_church') }}</label>
            <select name="church_id" class="form-select form-select-sm">
                <option value="">—</option>
                @foreach($churches as $church)
                    <option value="{{ $church->church_id }}" @selected((string) request('church_id') === (string) $church->church_id)>
                        {{ $church->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('pages.observability_filter_category') }}</label>
            <input type="text" name="category" value="{{ request('category') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('pages.observability_filter_severity') }}</label>
            <input type="text" name="severity" value="{{ request('severity') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-2">
            <label class="form-label">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary btn-sm w-100" type="submit">{{ __('Filter') }}</button>
        </div>
    </form>

    @if($tab === 'incidents')
        @if(!$incidents || $incidents->isEmpty())
            <div class="alert alert-light border">{{ __('pages.observability_no_incidents') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('pages.observability_last_seen') }}</th>
                            <th>{{ __('Severity') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Message') }}</th>
                            <th>{{ __('pages.observability_count') }}</th>
                            <th>{{ __('pages.observability_affected_users') }}</th>
                            <th>{{ __('pages.observability_affected_churches') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($incidents as $row)
                            <tr>
                                <td class="text-nowrap">{{ $row->last_seen }}</td>
                                <td><span class="badge text-bg-secondary">{{ $row->severity }}</span></td>
                                <td>{{ $row->category }}</td>
                                <td>
                                    <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($row->sample_message, 120) }}</div>
                                    <div class="text-muted small">{{ $row->exception_class }}</div>
                                </td>
                                <td>{{ $row->event_count }}</td>
                                <td>{{ $row->affected_users }}</td>
                                <td>{{ $row->affected_churches }}</td>
                                <td>
                                    <a class="btn btn-outline-secondary btn-sm"
                                       href="{{ route('superadmin.observability.index', array_merge(request()->query(), ['tab' => 'incidents', 'fingerprint' => $row->fingerprint])) }}">
                                        {{ __('pages.observability_affected_users') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $incidents->links() }}
        @endif

        @if(!empty($affectedUsers) && $affectedUsers->isNotEmpty())
            <h2 class="h5 mt-4">{{ __('pages.observability_affected_users') }}</h2>
            <ul class="list-group">
                @foreach($affectedUsers as $event)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $event->user?->email ?? ('#'.$event->user_id) }}</span>
                        <span class="text-muted small">{{ optional($event->occurred_at)->toDateTimeString() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    @elseif($tab === 'auth')
        @if(!$authFailures || $authFailures->isEmpty())
            <div class="alert alert-light border">{{ __('pages.observability_no_auth') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('When') }}</th>
                            <th>{{ __('Message') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Church') }}</th>
                            <th>{{ __('IP') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($authFailures as $event)
                            <tr>
                                <td class="text-nowrap">{{ optional($event->occurred_at)->toDateTimeString() }}</td>
                                <td>{{ $event->message }}</td>
                                <td>{{ data_get($event->context, 'email') }}</td>
                                <td>{{ $event->church_id }}</td>
                                <td class="small text-muted">{{ data_get($event->context, 'ip') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $authFailures->links() }}
        @endif
    @else
        <div class="alert alert-light border">{{ __('pages.observability_coming_soon') }}</div>
    @endif
</div>
@endsection
