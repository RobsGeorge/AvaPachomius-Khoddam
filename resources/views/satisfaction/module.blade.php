@extends('layouts.app')

@section('title', __('pages.satisfaction_report'))

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <h1 class="page-title mb-1">{{ __('pages.satisfaction_report') }}</h1>
    <p class="text-muted-theme mb-4">{{ $course->title }} — {{ $module->title }}</p>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="app-card card"><div class="card-body"><strong>{{ __('pages.total_responses') }}</strong><div class="fs-3">{{ $summary['total'] }}</div></div></div></div>
        @foreach(['lecture','speaker','workshop','timing','content'] as $key)
            <div class="col-md-4"><div class="app-card card"><div class="card-body"><strong>{{ __('pages.rate_'.$key) }}</strong><div class="fs-3">{{ $summary[$key]['average'] ?: '—' }}</div></div></div></div>
        @endforeach
    </div>

    <div class="app-card card">
        <div class="card-body">
            <h5 class="mb-3">{{ __('pages.individual_responses_admin') }}</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>{{ __('pages.user') }}</th>@foreach(['lecture','speaker','workshop','timing','content'] as $k)<th>{{ __('pages.rate_'.$k) }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($responses as $row)
                            <tr>
                                <td>{{ $row->user?->first_name }} {{ $row->user?->second_name }}</td>
                                @foreach(['lecture','speaker','workshop','timing','content'] as $k)
                                    <td>{{ $row->{$k.'_rating'} ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $responses->links() }}
        </div>
    </div>
</div>
@endsection
