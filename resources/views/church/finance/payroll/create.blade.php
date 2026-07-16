@extends('layouts.app')
@section('title', __('finance.new_run'))
@section('content')
<div class="container py-4" style="max-width:640px;">
    <h1 class="page-title mb-3">{{ __('finance.new_run') }}</h1>
    <form method="POST" action="{{ route('church.finance.payroll.store') }}" class="app-card card shadow-sm p-4">
        @csrf
        <div class="mb-3">
            <label class="form-label" for="period_start">{{ __('finance.period_start') }}</label>
            <input type="date" name="period_start" id="period_start" class="form-control" value="{{ old('period_start') }}" required>
            @error('period_start')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="period_end">{{ __('finance.period_end') }}</label>
            <input type="date" name="period_end" id="period_end" class="form-control" value="{{ old('period_end') }}" required>
            @error('period_end')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="currency">{{ __('finance.currency') }}</label>
            <input type="text" name="currency" id="currency" class="form-control" maxlength="3" value="{{ old('currency', 'EGP') }}" required>
            @error('currency')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label" for="notes">{{ __('finance.notes') }}</label>
            <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">{{ __('finance.save') }}</button>
            <a href="{{ route('church.finance.payroll.index') }}" class="btn btn-outline-secondary">{{ __('finance.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
