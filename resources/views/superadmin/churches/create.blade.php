@extends('layouts.app')

@section('title', __('tenancy.create_church'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h1 class="page-title mb-2">{{ __('tenancy.create_church') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('tenancy.create_church_intro') }}</p>

    <form method="POST" action="{{ route('superadmin.churches.store') }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="name">{{ __('tenancy.col_name') }}</label>
                <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" required maxlength="120">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label" for="slug">{{ __('tenancy.col_slug') }}</label>
                <input id="slug" name="slug" type="text" class="form-control @error('slug') is-invalid @enderror"
                       value="{{ old('slug') }}" required maxlength="40" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                       placeholder="st-mark">
                <div class="form-text">{{ __('tenancy.slug_hint') }}</div>
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="form-label" for="domain">{{ __('tenancy.custom_domain') }}</label>
                <input id="domain" name="domain" type="text" class="form-control @error('domain') is-invalid @enderror"
                       value="{{ old('domain') }}" maxlength="191" placeholder="optional.example.org">
                @error('domain')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <fieldset>
                <legend class="form-label">{{ __('tenancy.capabilities') }}</legend>
                <div class="row g-2">
                    @foreach($capabilities as $key => $def)
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="capabilities[]"
                                       value="{{ $key }}" id="cap-{{ $key }}"
                                       @checked(collect(old('capabilities', array_keys($capabilities)))->contains($key))>
                                <label class="form-check-label" for="cap-{{ $key }}">{{ __($def['label']) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label class="form-label" for="admin_user_ids">{{ __('tenancy.initial_admins') }}</label>
                <select id="admin_user_ids" name="admin_user_ids[]" class="form-select" multiple size="6">
                    @foreach($users as $user)
                        <option value="{{ $user->user_id }}" @selected(collect(old('admin_user_ids', []))->contains($user->user_id))>
                            {{ $user->email }} — {{ $user->first_name }} {{ $user->second_name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('tenancy.initial_admins_hint') }}</div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a href="{{ route('superadmin.churches.index') }}" class="btn btn-outline-secondary">{{ __('tenancy.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('tenancy.create_church') }}</button>
        </div>
    </form>
</div>
@endsection
