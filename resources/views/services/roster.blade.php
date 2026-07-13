@extends('layouts.app')

@section('title', __('service.roster_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="page-title h4 mb-1">{{ __('service.roster_title') }}</h1>
            <p class="text-muted-theme small mb-0">{{ __('service.roster_hint') }}</p>
        </div>
        @if($services->isNotEmpty())
            <form method="GET" action="{{ route('services.roster') }}" class="d-flex gap-2 align-items-center">
                <select name="service" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($services as $s)
                        <option value="{{ $s->service_id }}" @selected(($service->service_id ?? null) == $s->service_id)>
                            {{ $s->localizedTitle() }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if(! $service)
        <div class="app-card card shadow-sm">
            <div class="card-body text-muted">{{ __('service.no_services') }}</div>
        </div>
    @else
        <div class="app-card card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('pages.user') }}</th>
                            <th>{{ __('pages.email') ?? 'Email' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                            <tr>
                                <td>{{ $member->first_name }} {{ $member->second_name }}</td>
                                <td>{{ $member->email }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted small">{{ __('pages.no_records') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
