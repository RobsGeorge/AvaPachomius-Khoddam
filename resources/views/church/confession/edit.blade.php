@extends('layouts.app')
@section('title', __('church_mgmt.edit_slot'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.edit_slot') }}</h1>
    <form method="POST" action="{{ route('church.confession.update', $slot) }}" class="app-card card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="starts_at">{{ __('church_mgmt.starts_at') }}</label>
                <input type="datetime-local" name="starts_at" id="starts_at" class="form-control"
                       value="{{ old('starts_at', $slot->starts_at?->format('Y-m-d\TH:i')) }}" required>
            </div>
            <div>
                <label class="form-label" for="ends_at">{{ __('church_mgmt.ends_at') }}</label>
                <input type="datetime-local" name="ends_at" id="ends_at" class="form-control"
                       value="{{ old('ends_at', $slot->ends_at?->format('Y-m-d\TH:i')) }}" required>
            </div>
            <div>
                <label class="form-label" for="capacity">{{ __('church_mgmt.capacity') }}</label>
                <input type="number" name="capacity" id="capacity" class="form-control" value="{{ old('capacity', $slot->capacity) }}" min="1" max="50" required>
            </div>
            <div>
                <label class="form-label" for="location">{{ __('church_mgmt.location') }}</label>
                <input type="text" name="location" id="location" class="form-control" value="{{ old('location', $slot->location) }}">
            </div>
            <div>
                <label class="form-label" for="status">{{ __('church_mgmt.priest_status') }}</label>
                <select name="status" id="status" class="form-select">
                    <option value="open" @selected(old('status', $slot->status) === 'open')>{{ __('church_mgmt.status_open') }}</option>
                    <option value="closed" @selected(old('status', $slot->status) === 'closed')>{{ __('church_mgmt.status_closed') }}</option>
                    <option value="cancelled" @selected(old('status', $slot->status) === 'cancelled')>{{ __('church_mgmt.status_cancelled') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="notes">{{ __('church_mgmt.notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $slot->notes) }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.confession.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>
</div>
@endsection
