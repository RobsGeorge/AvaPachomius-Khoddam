@extends('layouts.app')
@section('title', __('church_mgmt.add_slot'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.add_slot') }}</h1>
    <form method="POST" action="{{ route('church.appointments.store') }}" class="app-card card shadow-sm">
        @csrf
        <input type="hidden" name="priest_id" value="{{ $priest->priest_id }}">
        <div class="card-body d-flex flex-column gap-3">
            <p class="text-muted-theme mb-0">{{ $priest->displayName() }}</p>
            <div>
                <label class="form-label" for="appointment_type_id">{{ __('church_mgmt.appointment_type') }}</label>
                <select name="appointment_type_id" id="appointment_type_id" class="form-select" required>
                    @foreach($types as $type)
                        <option value="{{ $type->appointment_type_id }}" @selected((int) old('appointment_type_id') === (int) $type->appointment_type_id)>
                            {{ $type->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="starts_at">{{ __('church_mgmt.starts_at') }}</label>
                <input type="datetime-local" name="starts_at" id="starts_at" class="form-control" value="{{ old('starts_at') }}" required>
            </div>
            <div>
                <label class="form-label" for="ends_at">{{ __('church_mgmt.ends_at') }}</label>
                <input type="datetime-local" name="ends_at" id="ends_at" class="form-control" value="{{ old('ends_at') }}" required>
            </div>
            <div>
                <label class="form-label" for="capacity">{{ __('church_mgmt.capacity') }}</label>
                <input type="number" name="capacity" id="capacity" class="form-control" value="{{ old('capacity', $types->first()?->default_capacity ?? 1) }}" min="1" max="50" required>
            </div>
            <div>
                <label class="form-label" for="location">{{ __('church_mgmt.location') }}</label>
                <input type="text" name="location" id="location" class="form-control" value="{{ old('location') }}">
            </div>
            <div>
                <label class="form-label" for="notes">{{ __('church_mgmt.notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.appointments.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>
</div>
@endsection
