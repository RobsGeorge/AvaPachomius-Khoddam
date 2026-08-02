@extends('layouts.app')

@section('title', __('pages.server_logs_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            @include('partials.superadmin-entry-tag', ['class' => 'fs-5'])
            <h1 class="page-title mb-0">{{ __('pages.server_logs_title') }}</h1>
        </div>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
        </a>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill"></i>
        {{ __('pages.server_logs_warning') }}
    </div>

    @if(empty($files))
        <div class="app-card card shadow-sm">
            <div class="card-body text-center text-muted py-5">
                {{ __('pages.server_logs_no_files') }}
            </div>
        </div>
    @else
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('pages.server_logs_file') }}</label>
                        <select name="file" class="form-select">
                            @foreach($files as $file)
                                <option value="{{ $file }}" @selected($selectedFile === $file)>{{ $file }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pages.server_logs_level') }}</label>
                        <select name="level" class="form-select">
                            <option value="">{{ __('pages.all') }}</option>
                            @foreach($levels as $lvl)
                                <option value="{{ $lvl }}" @selected($level === $lvl)>{{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pages.server_logs_search') }}</label>
                        <input type="text" name="q" class="form-control" value="{{ $search }}" placeholder="{{ __('pages.server_logs_search_placeholder') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">{{ __('pages.filter') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <p class="text-muted small">
            {{ __('pages.server_logs_showing_count', ['shown' => $entries->total(), 'total' => $totalEntries, 'file' => $selectedFile]) }}
        </p>

        <div class="app-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th style="white-space:nowrap;">{{ __('pages.server_logs_time') }}</th>
                                <th style="width:110px;">{{ __('pages.server_logs_level') }}</th>
                                <th>{{ __('pages.server_logs_message') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $entry)
                                @php
                                    $badgeClass = match ($entry['level']) {
                                        'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'bg-danger',
                                        'WARNING' => 'bg-warning text-dark',
                                        'NOTICE', 'INFO' => 'bg-info text-dark',
                                        default => 'bg-secondary',
                                    };
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ $entry['time'] }}</td>
                                    <td><span class="badge {{ $badgeClass }}">{{ $entry['level'] }}</span></td>
                                    <td>
                                        <pre class="mb-0 small bg-light p-2 rounded" style="white-space:pre-wrap; word-break:break-word; max-height:260px; overflow-y:auto;">{{ $entry['message'] }}</pre>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">{{ __('pages.server_logs_no_entries') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @include('partials.pagination', ['paginator' => $entries])
    @endif
</div>
@endsection
