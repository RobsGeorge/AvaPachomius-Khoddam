@php
    $isEdit = isset($plan);
    $entitlementMap = $entitlementMap ?? [];
@endphp
<div class="container py-4 animate-in" style="max-width:960px;">
    <h1 class="page-title mb-4">{{ $isEdit ? __('billing.edit_plan') : __('billing.create_plan') }}</h1>

    <form method="POST" action="{{ $isEdit ? route('superadmin.plans.update', $plan) : route('superadmin.plans.store') }}" class="app-card card shadow-sm">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">{{ __('billing.col_name') }}</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $plan->name ?? '') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ __('billing.col_slug') }}</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $plan->slug ?? '') }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('billing.col_tier') }}</label>
                <input type="number" name="tier_rank" class="form-control" value="{{ old('tier_rank', $plan->tier_rank ?? 0) }}" min="0">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('billing.col_seats') }}</label>
                <input type="number" name="includes_seats" class="form-control" value="{{ old('includes_seats', $plan->includes_seats ?? 50) }}" min="1" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('billing.col_status') }}</label>
                <select name="status" class="form-select">
                    @foreach(['draft','active','archived'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $plan->status ?? 'draft') === $st)>{{ __('billing.status_'.$st) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('billing.col_scope') }}</label>
                <select name="scope" class="form-select" required>
                    @foreach(['both','church','service'] as $sc)
                        <option value="{{ $sc }}" @selected(old('scope', $plan->scope ?? 'both') === $sc)>{{ __('billing.scope_'.$sc) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">{{ __('billing.description') }}</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $plan->description ?? '') }}</textarea>
            </div>

            <div class="col-12"><hr><h2 class="h6">{{ __('billing.entitlements') }}</h2></div>
            @foreach($features as $feature)
                @php
                    $def = $catalog[$feature->feature_key] ?? [];
                    $val = old('entitlements.'.$feature->feature_key, $entitlementMap[$feature->feature_key] ?? ($def['default'] ?? ''));
                @endphp
                <div class="col-md-6">
                    <label class="form-label">{{ __($feature->label_key) }}</label>
                    @if($feature->type === 'boolean')
                        <select name="entitlements[{{ $feature->feature_key }}]" class="form-select">
                            <option value="1" @selected(filter_var($val, FILTER_VALIDATE_BOOLEAN))>{{ __('tenancy.enabled') }}</option>
                            <option value="0" @selected(!filter_var($val, FILTER_VALIDATE_BOOLEAN))>{{ __('tenancy.disabled') }}</option>
                        </select>
                    @elseif($feature->type === 'enum')
                        <select name="entitlements[{{ $feature->feature_key }}]" class="form-select">
                            @foreach((array) $feature->enum_options as $opt)
                                <option value="{{ $opt }}" @selected((string)$val === (string)$opt)>{{ __('billing.mobile_app_'.$opt) }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="number" name="entitlements[{{ $feature->feature_key }}]" class="form-control" value="{{ $val }}" min="0" placeholder="{{ __('billing.unlimited') }}">
                    @endif
                </div>
            @endforeach

            <div class="col-12"><hr><h2 class="h6">{{ __('billing.prices') }}</h2></div>
            @php $priceRows = old('prices', $isEdit ? $plan->prices->map(fn($p) => $p->only(['billing_interval','amount_minor','currency','trial_days','is_default']))->values()->all() : [['billing_interval'=>'month','amount_minor'=>0,'currency'=>'EGP','trial_days'=>0,'is_default'=>true]]); @endphp
            @foreach($priceRows as $i => $price)
                <div class="col-md-3">
                    <label class="form-label">{{ __('billing.col_interval') }}</label>
                    <select name="prices[{{ $i }}][billing_interval]" class="form-select">
                        <option value="month" @selected(($price['billing_interval'] ?? '') === 'month')>month</option>
                        <option value="year" @selected(($price['billing_interval'] ?? '') === 'year')>year</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('billing.col_price') }} (minor)</label>
                    <input type="number" name="prices[{{ $i }}][amount_minor]" class="form-control" value="{{ $price['amount_minor'] ?? 0 }}" min="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Currency</label>
                    <input type="text" name="prices[{{ $i }}][currency]" class="form-control" value="{{ $price['currency'] ?? 'EGP' }}" maxlength="3">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Trial days</label>
                    <input type="number" name="prices[{{ $i }}][trial_days]" class="form-control" value="{{ $price['trial_days'] ?? 0 }}" min="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="prices[{{ $i }}][is_default]" value="1" @checked(!empty($price['is_default']))>
                        <label class="form-check-label">Default</label>
                    </div>
                </div>
            @endforeach

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">{{ __('tenancy.save') }}</button>
                <a href="{{ route('superadmin.plans.index') }}" class="btn btn-outline-secondary">{{ __('tenancy.back') }}</a>
            </div>
        </div>
    </form>
</div>
