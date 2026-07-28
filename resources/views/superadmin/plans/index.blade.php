@extends('layouts.app')

@section('title', __('billing.plans_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:1100px;">
    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('billing.plans_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('billing.plans_intro') }}</p>
        </div>
        <a href="{{ route('superadmin.plans.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('billing.create_plan') }}
        </a>
    </div>

    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('billing.col_name') }}</th>
                        <th>{{ __('billing.col_slug') }}</th>
                        <th>{{ __('billing.col_tier') }}</th>
                        <th>{{ __('billing.col_seats') }}</th>
                        <th>{{ __('billing.col_status') }}</th>
                        <th>{{ __('billing.col_subscribers') }}</th>
                        <th>{{ __('billing.col_price') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        @php $defaultPrice = $plan->prices->firstWhere('is_default', true) ?? $plan->prices->first(); @endphp
                        <tr>
                            <td class="fw-semibold">{{ $plan->name }}</td>
                            <td><code>{{ $plan->slug }}</code></td>
                            <td>{{ $plan->tier_rank }}</td>
                            <td>{{ $plan->includes_seats }}</td>
                            <td><span class="badge bg-secondary">{{ __('billing.status_'.$plan->status) }}</span></td>
                            <td>{{ $plan->subscriptions_count }}</td>
                            <td>
                                @if($defaultPrice)
                                    {{ number_format($defaultPrice->amount_minor / 100, 2) }} {{ $defaultPrice->currency }}/{{ $defaultPrice->billing_interval }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('superadmin.plans.show', $plan) }}" class="btn btn-sm btn-outline-secondary">{{ __('billing.show') }}</a>
                                <a href="{{ route('superadmin.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">{{ __('tenancy.edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted-theme p-4">{{ __('billing.no_subscription') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
