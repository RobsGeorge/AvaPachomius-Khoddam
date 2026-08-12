@extends('layouts.app')
@section('title', $type ? __('church_mgmt.edit_appointment_type') : __('church_mgmt.add_appointment_type'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ $type ? __('church_mgmt.edit_appointment_type') : __('church_mgmt.add_appointment_type') }}</h1>
    <form method="POST"
          action="{{ $type ? route('church.appointments.types.update', $type) : route('church.appointments.types.store') }}"
          class="app-card card shadow-sm">
        @csrf
        @if($type) @method('PUT') @endif
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="name_ar">{{ __('church_mgmt.name_ar') }}</label>
                <input type="text" name="name_ar" id="name_ar" class="form-control" value="{{ old('name_ar', $type?->name_ar) }}" required maxlength="120">
            </div>
            <div>
                <label class="form-label" for="name_en">{{ __('church_mgmt.name_en') }}</label>
                <input type="text" name="name_en" id="name_en" class="form-control" value="{{ old('name_en', $type?->name_en) }}" maxlength="120">
            </div>
            <div>
                <label class="form-label" for="slug">{{ __('church_mgmt.slug') }}</label>
                <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $type?->slug) }}" maxlength="80" @if(!$type) placeholder="{{ __('church_mgmt.slug_auto') }}" @endif @if($type) required @endif>
            </div>
            <div>
                <label class="form-label" for="default_capacity">{{ __('church_mgmt.capacity') }}</label>
                <input type="number" name="default_capacity" id="default_capacity" class="form-control" value="{{ old('default_capacity', $type?->default_capacity ?? 1) }}" min="1" max="50" required>
            </div>
            <div>
                <label class="form-label" for="default_duration_minutes">{{ __('church_mgmt.duration_minutes') }}</label>
                <input type="number" name="default_duration_minutes" id="default_duration_minutes" class="form-control" value="{{ old('default_duration_minutes', $type?->default_duration_minutes ?? 60) }}" min="15" max="480" required>
            </div>
            <div>
                <label class="form-label" for="status">{{ __('church_mgmt.priest_status') }}</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="active" @selected(old('status', $type?->status ?? 'active') === 'active')>{{ __('church_mgmt.status_active') }}</option>
                    <option value="inactive" @selected(old('status', $type?->status) === 'inactive')>{{ __('church_mgmt.status_inactive') }}</option>
                </select>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.appointments.types.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>
</div>
@endsection
