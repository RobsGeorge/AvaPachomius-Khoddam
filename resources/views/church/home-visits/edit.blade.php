@extends('layouts.app')
@section('title', __('church_mgmt.edit_visit'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.edit_visit') }}</h1>
    <form method="POST" action="{{ route('church.home-visits.update', $visit) }}" class="app-card card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="subject_name">{{ __('church_mgmt.subject_name') }}</label>
                <input type="text" name="subject_name" id="subject_name" class="form-control" value="{{ old('subject_name', $visit->subject_name) }}" required>
            </div>
            <div>
                <label class="form-label" for="address">{{ __('church_mgmt.address') }}</label>
                <input type="text" name="address" id="address" class="form-control" value="{{ old('address', $visit->address) }}">
            </div>
            <div>
                <label class="form-label" for="scheduled_at">{{ __('church_mgmt.scheduled_at') }}</label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control"
                       value="{{ old('scheduled_at', $visit->scheduled_at?->format('Y-m-d\TH:i')) }}" required>
            </div>
            <div>
                <label class="form-label" for="duration_min">{{ __('church_mgmt.duration_min') }}</label>
                <input type="number" name="duration_min" id="duration_min" class="form-control" value="{{ old('duration_min', $visit->duration_min) }}" min="15" max="480">
            </div>
            <div>
                <label class="form-label" for="status">{{ __('church_mgmt.priest_status') }}</label>
                <select name="status" id="status" class="form-select">
                    <option value="scheduled" @selected(old('status', $visit->status) === 'scheduled')>{{ __('church_mgmt.status_scheduled') }}</option>
                    <option value="done" @selected(old('status', $visit->status) === 'done')>{{ __('church_mgmt.status_done') }}</option>
                    <option value="cancelled" @selected(old('status', $visit->status) === 'cancelled')>{{ __('church_mgmt.status_cancelled') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="notes">{{ __('church_mgmt.notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $visit->notes) }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.home-visits.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>
</div>
@endsection
