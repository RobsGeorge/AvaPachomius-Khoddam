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

    <p class="text-muted-theme mb-3">{{ __('pages.server_logs_intro') }}</p>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <a href="{{ route('superadmin.server-logs.index', ['level' => 'errors']) }}"
           class="btn btn-sm {{ $levelFilter === 'errors' ? 'btn-danger' : 'btn-outline-danger' }}">
            {{ __('pages.server_logs_filter_errors') }}
        </a>
        <a href="{{ route('superadmin.server-logs.index', ['level' => 'all']) }}"
           class="btn btn-sm {{ $levelFilter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">
            {{ __('pages.server_logs_filter_all') }}
        </a>
        <a href="{{ route('superadmin.server-logs.index', ['level' => $levelFilter]) }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-clockwise"></i> {{ __('pages.server_logs_refresh') }}
        </a>
        @if(!empty($logFiles))
            <span class="small text-muted-theme ms-1">
                {{ __('pages.server_logs_sources', ['files' => implode(', ', $logFiles)]) }}
            </span>
        @endif
    </div>

    @if(empty($entries))
        <div class="alert alert-secondary border-0">
            <i class="bi bi-info-circle"></i> {{ __('pages.server_logs_empty') }}
        </div>
    @else
        <div class="table-responsive d-none d-lg-block admin-table-desktop app-card card shadow-sm">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:11rem;">{{ __('pages.server_logs_time') }}</th>
                        <th style="width:7rem;">{{ __('pages.server_logs_level') }}</th>
                        <th>{{ __('pages.server_logs_message') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td class="text-nowrap small font-monospace">{{ $entry['time'] }}</td>
                            <td>
                                @php
                                    $badge = match ($entry['level']) {
                                        'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'danger',
                                        'WARNING' => 'warning text-dark',
                                        'NOTICE', 'INFO' => 'info',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ $entry['level'] }}</span>
                            </td>
                            <td class="small" style="white-space: pre-wrap; word-break: break-word;">{{ $entry['message'] !== '' ? $entry['message'] : __('pages.server_logs_empty_message') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-lg-none">
            @foreach($entries as $entry)
                <div class="app-card card shadow-sm mb-2">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between gap-2 mb-1">
                            <span class="small font-monospace text-muted-theme">{{ $entry['time'] }}</span>
                            @php
                                $badge = match ($entry['level']) {
                                    'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'danger',
                                    'WARNING' => 'warning text-dark',
                                    'NOTICE', 'INFO' => 'info',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}">{{ $entry['level'] }}</span>
                        </div>
                        <div class="small" style="white-space: pre-wrap; word-break: break-word;">{{ $entry['message'] !== '' ? $entry['message'] : __('pages.server_logs_empty_message') }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
