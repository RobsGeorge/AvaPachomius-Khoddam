@extends('layouts.app')
@section('title', __('church_mgmt.add_visit'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.add_visit') }}</h1>
    <form method="POST" action="{{ route('church.home-visits.store') }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="subject_name">{{ __('church_mgmt.subject_name') }}</label>
                <input type="text" name="subject_name" id="subject_name" class="form-control" value="{{ old('subject_name') }}" required>
            </div>
            <div>
                <label class="form-label" for="address">{{ __('church_mgmt.address') }}</label>
                <input type="text" name="address" id="address" class="form-control" value="{{ old('address') }}">
            </div>
            <div>
                <label class="form-label" for="scheduled_at">{{ __('church_mgmt.scheduled_at') }}</label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control" value="{{ old('scheduled_at') }}" required>
            </div>
            <div>
                <label class="form-label" for="duration_min">{{ __('church_mgmt.duration_min') }}</label>
                <input type="number" name="duration_min" id="duration_min" class="form-control" value="{{ old('duration_min', 60) }}" min="15" max="480">
            </div>
            <div>
                <label class="form-label" for="assigned_user_email">{{ __('church_mgmt.assignee_email') }}</label>
                <input type="email" name="assigned_user_email" id="assigned_user_email" class="form-control" value="{{ old('assigned_user_email') }}">
            </div>
            <div>
                <label class="form-label" for="notes">{{ __('church_mgmt.notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.home-visits.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>
</div>
@endsection
