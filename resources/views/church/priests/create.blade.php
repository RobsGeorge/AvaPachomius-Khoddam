@extends('layouts.app')
@section('title', __('church_mgmt.add_priest'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.add_priest') }}</h1>
    <form method="POST" action="{{ route('church.priests.store') }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="email">{{ __('church_mgmt.priest_email') }}</label>
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div>
                <label class="form-label" for="title">{{ __('church_mgmt.priest_title') }}</label>
                <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}">
            </div>
            <div>
                <label class="form-label" for="status">{{ __('church_mgmt.priest_status') }}</label>
                <select name="status" id="status" class="form-select">
                    <option value="active">{{ __('church_mgmt.status_active') }}</option>
                    <option value="inactive">{{ __('church_mgmt.status_inactive') }}</option>
                </select>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.priests.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>
</div>
@endsection
