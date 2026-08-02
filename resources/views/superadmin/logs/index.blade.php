@extends('layouts.app')

@section('title', __('pages.application_logs_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            @include('partials.superadmin-entry-tag', ['class' => 'fs-5'])
            <h1 class="page-title mb-0">{{ __('pages.application_logs_title') }}</h1>
        </div>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
        </a>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill"></i>
        {{ __('pages.application_logs_warning') }}
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="file">{{ __('pages.application_logs_file') }}</label>
                    <select name="file" id="file" class="form-select">
                        @forelse($availableFiles as $basename => $label)
                            <option value="{{ $basename }}" @selected($selectedFile === $basename)>{{ $basename }}</option>
                        @empty
                            <option value="laravel.log">laravel.log</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="level">{{ __('pages.application_logs_level') }}</label>
                    <select name="level" id="level" class="form-select">
                        <option value="">{{ __('pages.all') }}</option>
                        @foreach(['ERROR', 'WARNING', 'INFO', 'DEBUG', 'CRITICAL', 'ALERT', 'EMERGENCY'] as $logLevel)
                            <option value="{{ $logLevel }}" @selected($level === $logLevel)>{{ $logLevel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lines">{{ __('pages.application_logs_lines') }}</label>
                    <input type="number" name="lines" id="lines" class="form-control" min="10" max="500" value="{{ $lines }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">{{ __('pages.filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 class="h6 mb-0">{{ __('pages.application_logs_entries', ['file' => $selectedFile]) }}</h2>
                <span class="badge bg-secondary">{{ count($entries) }}</span>
            </div>

            @if($missingFile)
                <p class="text-muted-theme mb-0">{{ __('pages.application_logs_missing_file', ['file' => $selectedFile]) }}</p>
            @elseif($entries === [])
                <p class="text-muted-theme mb-0">{{ __('pages.application_logs_empty') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 11rem;">{{ __('pages.application_logs_col_time') }}</th>
                                <th style="width: 6rem;">{{ __('pages.application_logs_col_level') }}</th>
                                <th>{{ __('pages.application_logs_col_message') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entries as $entry)
                                <tr>
                                    <td class="text-nowrap small text-muted-theme">{{ $entry['timestamp'] ?? '—' }}</td>
                                    <td>
                                        @if(!empty($entry['level']))
                                            @php
                                                $badge = match($entry['level']) {
                                                    'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => 'danger',
                                                    'WARNING' => 'warning',
                                                    'INFO' => 'info',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge bg-{{ $badge }}">{{ $entry['level'] }}</span>
                                        @else
                                            <span class="text-muted-theme">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <pre class="mb-0 small" style="white-space:pre-wrap;word-break:break-word;">{{ $entry['message'] }}</pre>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
