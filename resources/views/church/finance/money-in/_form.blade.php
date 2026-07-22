@php
    use App\Support\Money;
    $amount = old('amount', $entry ? Money::fromMinor((int) $entry->amount_minor) : '');
@endphp
<form method="POST" action="{{ $action }}" class="app-card card shadow-sm p-4">
    @csrf
    @if(($method ?? 'POST') !== 'POST')
        @method($method)
    @endif
    <div class="mb-3">
        <label class="form-label" for="source">{{ __('finance.source') }}</label>
        <input type="text" name="source" id="source" class="form-control" value="{{ old('source', $entry->source ?? '') }}" required>
        @error('source')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="category">{{ __('finance.category') }}</label>
        <input type="text" name="category" id="category" class="form-control" value="{{ old('category', $entry->category ?? '') }}" required>
        @error('category')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="amount">{{ __('finance.amount') }}</label>
        <input type="text" name="amount" id="amount" class="form-control" inputmode="decimal" value="{{ $amount }}" required>
        @error('amount')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label" for="currency">{{ __('finance.currency') }}</label>
            <input type="text" name="currency" id="currency" class="form-control" maxlength="3" value="{{ old('currency', $entry->currency ?? 'EGP') }}" required>
            @error('currency')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="fx_rate">{{ __('finance.fx_rate') }}</label>
            <input type="text" name="fx_rate" id="fx_rate" class="form-control" value="{{ old('fx_rate', $entry->fx_rate ?? '1') }}" required>
            @error('fx_rate')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="received_at">{{ __('finance.received_at') }}</label>
        <input type="date" name="received_at" id="received_at" class="form-control" value="{{ old('received_at', optional($entry->received_at ?? null)->format('Y-m-d')) }}" required>
        @error('received_at')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="notes">{{ __('finance.notes') }}</label>
        <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $entry->notes ?? '') }}</textarea>
    </div>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ __('finance.save') }}</button>
        <a href="{{ route('church.finance.money-in.index') }}" class="btn btn-outline-secondary">{{ __('finance.cancel') }}</a>
    </div>
</form>
