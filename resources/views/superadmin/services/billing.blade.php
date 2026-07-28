@extends('layouts.app')

@section('title', __('billing.billing').' — '.$service->localizedTitle())

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('billing.billing') }}: {{ $service->localizedTitle() }}</h1>
            <p class="text-muted-theme mb-0">
                {{ $church?->name }}
                · {{ __('billing.service_addon_hint') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            @if($church)
                <a href="{{ route('superadmin.churches.billing', $church) }}" class="btn btn-outline-secondary btn-sm">{{ __('billing.church_billing') }}</a>
            @endif
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('billing.current_plan') }}</div>
                <div class="card-body">
                    @if($service->subscription)
                        <p class="mb-1"><strong>{{ $service->subscription->plan?->name ?? '—' }}</strong></p>
                        <p class="text-muted-theme small mb-0">{{ __('billing.subscription_status') }}: {{ $service->subscription->status }}</p>
                        <p class="text-muted-theme small mb-0">
                            {{ __('billing.payer') }}:
                            {{ $service->subscription->paysIndependently() ? __('billing.payer_service') : __('billing.payer_church') }}
                        </p>
                    @else
                        <p class="text-muted-theme mb-0">{{ __('billing.no_subscription') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('billing.merged_entitlements') }}</div>
                <div class="card-body small text-muted-theme">
                    {{ __('billing.merged_entitlements_hint') }}
                </div>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('billing.assign_plan') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.services.billing.assign', $service) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Plan</label>
                    <select name="plan_id" class="form-select" required>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->plan_id }}" @selected($service->subscription?->plan_id === $plan->plan_id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">{{ __('billing.subscription_status') }}</label>
                    <select name="status" class="form-select">
                        <option value="active">active</option>
                        <option value="trialing">trialing</option>
                        <option value="comped">comped</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('billing.comp_reason') }}</label>
                    <input type="text" name="comp_reason" class="form-control">
                </div>
                <div class="col-md-2">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="independent_payer" value="1" id="independent_payer"
                               @checked($service->subscription?->paysIndependently())>
                        <label class="form-check-label" for="independent_payer">{{ __('billing.independent_payer') }}</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">{{ __('billing.assign_plan') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('billing.merged_entitlements') }}</div>
        <ul class="list-group list-group-flush">
            @foreach($features as $feature)
                <li class="list-group-item d-flex justify-content-between">
                    <span>{{ __($feature->label_key) }}</span>
                    <code class="small">{{ json_encode($resolved[$feature->feature_key] ?? null) }}</code>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="app-card card shadow-sm">
        <div class="card-header fw-semibold">{{ __('billing.overrides') }}</div>
        <div class="card-body border-bottom">
            <form method="POST" action="{{ route('superadmin.services.billing.overrides.store', $service) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">{{ __('billing.col_feature') }}</label>
                    <select name="feature_key" class="form-select" required>
                        @foreach($features as $feature)
                            <option value="{{ $feature->feature_key }}">{{ __($feature->label_key) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('billing.col_value') }}</label>
                    <input type="text" name="value" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('billing.override_reason') }}</label>
                    <input type="text" name="reason" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">{{ __('billing.add_override') }}</button>
                </div>
            </form>
        </div>
        <ul class="list-group list-group-flush">
            @forelse($overrides as $override)
                <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                    <span><code>{{ $override->feature_key }}</code> = {{ json_encode($override->resolvedValue()) }}</span>
                    <form method="POST" action="{{ route('superadmin.services.billing.overrides.destroy', [$service, $override->feature_key]) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">&times;</button>
                    </form>
                </li>
            @empty
                <li class="list-group-item text-muted-theme">—</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
