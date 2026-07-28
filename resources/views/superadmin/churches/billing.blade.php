@extends('layouts.app')

@section('title', __('billing.billing').' — '.$church->name)

@section('content')
<div class="container py-4 animate-in" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('billing.billing') }}: {{ $church->name }}</h1>
            <p class="text-muted-theme mb-0">{{ __('billing.subscription_managed_hint') }}</p>
        </div>
        <a href="{{ route('superadmin.churches.show', $church) }}" class="btn btn-outline-secondary btn-sm">{{ __('tenancy.back') }}</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('billing.current_plan') }}</div>
                <div class="card-body">
                    @if($church->subscription)
                        <p class="mb-1"><strong>{{ $church->subscription->plan?->name ?? '—' }}</strong></p>
                        <p class="text-muted-theme small mb-0">{{ __('billing.subscription_status') }}: {{ $church->subscription->status }}</p>
                    @else
                        <p class="text-muted-theme mb-0">{{ __('billing.no_subscription') }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="app-card card shadow-sm h-100">
                <div class="card-header fw-semibold">{{ __('billing.usage') }}</div>
                <div class="card-body">
                    <p class="mb-1">{{ __('billing.seats_used') }}: {{ $seatUsed }} / {{ $seatLimit ?? __('billing.unlimited') }}</p>
                    <p class="mb-0 text-muted-theme small">{{ __('billing.storage_used') }}: {{ number_format($storageUsed / 1024 / 1024, 1) }} MB / {{ $storageLimit ? number_format($storageLimit / 1024 / 1024, 1).' MB' : __('billing.unlimited') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('billing.assign_plan') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('superadmin.churches.billing.assign', $church) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Plan</label>
                    <select name="plan_id" class="form-select" required>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->plan_id }}" @selected($church->subscription?->plan_id === $plan->plan_id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
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
                    <button type="submit" class="btn btn-primary w-100">{{ __('billing.assign_plan') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('billing.entitlements') }}</div>
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
            <form method="POST" action="{{ route('superadmin.churches.billing.overrides.store', $church) }}" class="row g-2 align-items-end">
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
                    <form method="POST" action="{{ route('superadmin.churches.billing.overrides.destroy', [$church, $override->feature_key]) }}">
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
