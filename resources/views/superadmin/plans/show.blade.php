@extends('layouts.app')

@section('title', $plan->name)

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ $plan->name }}</h1>
            <p class="text-muted-theme mb-0"><code>{{ $plan->slug }}</code> · {{ __('billing.status_'.$plan->status) }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('superadmin.plans.edit', $plan) }}" class="btn btn-outline-primary btn-sm">{{ __('billing.edit_plan') }}</a>
            <a href="{{ route('superadmin.plans.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('tenancy.back') }}</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('billing.entitlements') }}</div>
                <ul class="list-group list-group-flush">
                    @foreach($features as $feature)
                        @php $val = $plan->entitlements->firstWhere('feature_key', $feature->feature_key)?->resolvedValue(); @endphp
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ __($feature->label_key) }}</span>
                            <code class="small">{{ is_bool($val) ? ($val ? 'true' : 'false') : ($val ?? '—') }}</code>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('billing.prices') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse($plan->prices as $price)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $price->billing_interval }} @if($price->is_default)<span class="badge bg-primary">default</span>@endif</span>
                            <span>{{ number_format($price->amount_minor / 100, 2) }} {{ $price->currency }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted-theme">—</li>
                    @endforelse
                </ul>
            </div>
            <div class="app-card card shadow-sm mt-4">
                <div class="card-header fw-semibold">{{ __('billing.col_subscribers') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse($plan->subscriptions as $sub)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('superadmin.churches.show', $sub->church) }}">{{ $sub->church->name ?? $sub->church_id }}</a>
                            <span class="badge bg-secondary">{{ $sub->status }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted-theme">—</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
