@extends('layouts.app')

@section('title', __('tenancy.edit_church'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h1 class="page-title mb-4">{{ __('tenancy.edit_church') }} — {{ $church->name }}</h1>

    <form method="POST" action="{{ route('superadmin.churches.update', $church) }}" class="app-card card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label">{{ __('tenancy.col_slug') }}</label>
                <input type="text" class="form-control" value="{{ $church->slug }}" disabled>
            </div>
            <div>
                <label class="form-label" for="name">{{ __('tenancy.col_name') }}</label>
                <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $church->name) }}" required maxlength="120">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label" for="domain">{{ __('tenancy.custom_domain') }}</label>
                <input id="domain" name="domain" type="text" class="form-control"
                       value="{{ old('domain', $church->domain) }}" maxlength="191">
            </div>
            <div>
                <label class="form-label" for="status">{{ __('tenancy.col_status') }}</label>
                <select id="status" name="status" class="form-select">
                    <option value="active" @selected(old('status', $church->status) === 'active')>{{ __('tenancy.status_active') }}</option>
                    <option value="suspended" @selected(old('status', $church->status) === 'suspended')>{{ __('tenancy.status_suspended') }}</option>
                </select>
            </div>
            <fieldset>
                <legend class="form-label">{{ __('tenancy.capabilities') }}</legend>
                <div class="row g-2">
                    @foreach($capabilities as $key => $def)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="capabilities[]"
                                       value="{{ $key }}" id="cap-{{ $key }}"
                                       @checked(collect(old('capabilities', $enabledCapabilities))->contains($key))>
                                <label class="form-check-label" for="cap-{{ $key }}">{{ __($def['label']) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a href="{{ route('superadmin.churches.show', $church) }}" class="btn btn-outline-secondary">{{ __('tenancy.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('tenancy.save') }}</button>
        </div>
    </form>
</div>
@endsection
