@extends('layouts.app')

@section('title', __('pages.course_satisfaction_report'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <h1 class="page-title mb-4">{{ __('pages.course_satisfaction_report') }} — {{ $course->title }}</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="app-card card"><div class="card-body"><strong>{{ __('pages.total_responses') }}</strong><div class="fs-3">{{ $summary['total'] }}</div></div></div></div>
        @foreach(['lecture','speaker','workshop','timing','content'] as $key)
            <div class="col-md-4"><div class="app-card card"><div class="card-body"><strong>{{ __('pages.rate_'.$key) }}</strong><div class="fs-3">{{ $summary[$key]['average'] ?: '—' }}</div></div></div></div>
        @endforeach
    </div>

    <div class="app-card card">
        <div class="card-body">
            <h5>{{ __('pages.by_module') }}</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>{{ __('pages.module') }}</th><th>{{ __('pages.responses') }}</th>@foreach(['lecture','speaker','workshop','timing','content'] as $k)<th>{{ __('pages.rate_'.$k) }}</th>@endforeach<th></th></tr></thead>
                    <tbody>
                        @foreach($byModule as $row)
                            @php $mod = $modules[$row->module_id] ?? null; @endphp
                            <tr>
                                <td>{{ $mod?->title ?? $row->module_id }}</td>
                                <td>{{ $row->response_count }}</td>
                                <td>{{ round($row->avg_lecture, 2) ?: '—' }}</td>
                                <td>{{ round($row->avg_speaker, 2) ?: '—' }}</td>
                                <td>{{ round($row->avg_workshop, 2) ?: '—' }}</td>
                                <td>{{ round($row->avg_timing, 2) ?: '—' }}</td>
                                <td>{{ round($row->avg_content, 2) ?: '—' }}</td>
                                <td><a href="{{ route('satisfaction.module', [$course->course_id, $row->module_id]) }}" class="btn btn-sm btn-outline-theme">{{ __('pages.details') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
