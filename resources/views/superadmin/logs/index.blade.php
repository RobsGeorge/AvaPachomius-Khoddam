@extends('layouts.app')

@section('title', __('pages.application_logs_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            @include('partials.superadmin-entry-tag', ['class' => 'fs-5'])
            <div>
                <h1 class="page-title mb-0">{{ __('pages.application_logs_title') }}</h1>
                <p class="text-muted mb-0 small">{{ __('pages.application_logs_intro') }}</p>
            </div>
        </div>
        <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-right" aria-hidden="true"></i> {{ __('pages.back_to_superadmin') }}
        </a>
    </div>

    <div class="alert alert-warning border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        {{ __('pages.application_logs_warning') }}
    </div>

    @if(empty($files))
        <div class="app-card card shadow-sm">
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-file-earmark-text fs-2 d-block mb-2" aria-hidden="true"></i>
                {{ __('pages.application_logs_no_files') }}
            </div>
        </div>
    @else
        <div class="app-card card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="log-file">{{ __('pages.application_logs_file') }}</label>
                        <select name="file" id="log-file" class="form-select" onchange="this.form.submit()">
                            @foreach($files as $file)
                                <option value="{{ $file['name'] }}" @selected($selectedFile === $file['name'])>
                                    {{ $file['name'] }} — {{ $file['size_label'] }} — {{ $file['modified_at']->format('Y-m-d H:i') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="log-level">{{ __('pages.application_logs_level') }}</label>
                        <select name="level" id="log-level" class="form-select">
                            <option value="">{{ __('pages.all') }}</option>
                            @foreach($levels as $option)
                                <option value="{{ $option }}" @selected($level === $option)>
                                    {{ $option === \App\Services\ApplicationLogReaderService::LEVEL_NONE ? __('pages.application_logs_level_none') : strtoupper($option) }}
                                    ({{ $levelCounts[$option] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="log-search">{{ __('pages.application_logs_search') }}</label>
                        <input type="text" name="q" id="log-search" class="form-control"
                               value="{{ $search }}" placeholder="{{ __('pages.application_logs_search_hint') }}">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">{{ __('pages.filter') }}</button>
                        <a href="{{ route('superadmin.logs.index', ['file' => $selectedFile]) }}"
                           class="btn btn-outline-secondary" title="{{ __('pages.application_logs_reset') }}"
                           aria-label="{{ __('pages.application_logs_reset') }}">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <p class="text-muted small mb-0">
                {{ __('pages.application_logs_summary', ['matches' => $matchCount, 'scanned' => $totalScanned, 'file' => $selectedFile]) }}
            </p>
            <a href="{{ route('superadmin.logs.index', request()->query()) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> {{ __('pages.application_logs_refresh') }}
            </a>
        </div>

        @if($truncated)
            <div class="alert alert-info border-0 shadow-sm py-2 small">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                {{ __('pages.application_logs_truncated', ['size' => $tailLimitLabel]) }}
            </div>
        @endif

        <div class="app-card card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive d-none d-lg-block admin-table-desktop">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th style="width:170px;">{{ __('pages.application_logs_col_time') }}</th>
                                <th style="width:110px;">{{ __('pages.application_logs_col_level') }}</th>
                                <th>{{ __('pages.application_logs_col_message') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries as $entry)
                                <tr>
                                    <td class="text-nowrap" dir="ltr">
                                        {{ $entry['time']?->format('Y-m-d H:i:s') ?? __('pages.application_logs_no_time') }}
                                    </td>
                                    <td>
                                        @if($entry['level'])
                                            <span class="badge bg-{{ $entry['variant'] }}">{{ strtoupper($entry['level']) }}</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ __('pages.application_logs_level_none') }}</span>
                                        @endif
                                    </td>
                                    <td style="word-break:break-word;">
                                        <span dir="ltr" class="d-inline-block">{{ $entry['message'] }}</span>
                                        @if($entry['detail'])
                                            <details class="mt-1">
                                                <summary class="text-muted small">{{ __('pages.application_logs_show_details') }}</summary>
                                                <pre class="mb-0 small bg-light p-2 rounded" dir="ltr"
                                                     style="white-space:pre-wrap; max-height:320px; overflow:auto;">{{ $entry['detail'] }}</pre>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">
                                        {{ $isFiltered ? __('pages.application_logs_empty') : __('pages.application_logs_file_empty') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-lg-none admin-data-cards p-3">
                    @forelse($entries as $entry)
                        <article class="data-card">
                            <div class="data-card-title" dir="ltr" style="word-break:break-word;">{{ $entry['message'] }}</div>
                            <dl class="data-meta-list mb-0">
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.application_logs_col_time') }}</dt>
                                    <dd dir="ltr">{{ $entry['time']?->format('Y-m-d H:i:s') ?? __('pages.application_logs_no_time') }}</dd>
                                </div>
                                <div class="data-meta-row">
                                    <dt>{{ __('pages.application_logs_col_level') }}</dt>
                                    <dd>
                                        @if($entry['level'])
                                            <span class="badge bg-{{ $entry['variant'] }}">{{ strtoupper($entry['level']) }}</span>
                                        @else
                                            <span class="badge bg-light text-dark">{{ __('pages.application_logs_level_none') }}</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                            @if($entry['detail'])
                                <details class="mt-2">
                                    <summary class="text-muted small">{{ __('pages.application_logs_show_details') }}</summary>
                                    <pre class="mb-0 small bg-light p-2 rounded" dir="ltr"
                                         style="white-space:pre-wrap; max-height:260px; overflow:auto;">{{ $entry['detail'] }}</pre>
                                </details>
                            @endif
                        </article>
                    @empty
                        <p class="text-center py-4 text-muted mb-0">
                            {{ $isFiltered ? __('pages.application_logs_empty') : __('pages.application_logs_file_empty') }}
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        @include('partials.pagination', ['paginator' => $entries])
    @endif
</div>
@endsection
