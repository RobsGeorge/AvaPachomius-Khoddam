@extends('layouts.app')
@section('title', __('church_mgmt.edit_slot'))
@section('content')
<div class="container py-4" style="max-width:640px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.edit_slot') }}</h1>
    <form method="POST" action="{{ route('church.appointments.update', $slot) }}" class="app-card card shadow-sm mb-3">
        @csrf
        @method('PUT')
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="appointment_type_id">{{ __('church_mgmt.appointment_type') }}</label>
                <select name="appointment_type_id" id="appointment_type_id" class="form-select" required>
                    @foreach($types as $type)
                        <option value="{{ $type->appointment_type_id }}" @selected((int) old('appointment_type_id', $slot->appointment_type_id) === (int) $type->appointment_type_id)>
                            {{ $type->displayName() }}
                        </option>
                    @endforeach
                </select>
            </div>
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
                <label class="form-label" for="status">{{ __('church_mgmt.slot_status') }}</label>
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
            <a href="{{ route('church.appointments.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.save') }}</button>
        </div>
    </form>

    @if($slot->confirmedBookings->isNotEmpty())
        <div class="app-card card shadow-sm">
            <div class="card-header">{{ __('church_mgmt.bookings_list') }}</div>
            <ul class="list-group list-group-flush">
                @foreach($slot->confirmedBookings as $booking)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ trim(($booking->user->first_name ?? '').' '.($booking->user->second_name ?? '')) ?: $booking->user->email }}</div>
                        @if($booking->notes)<div class="small text-muted-theme">{{ $booking->notes }}</div>@endif
                        <form method="POST" action="{{ route('church.appointments.bookings.cancel', $booking) }}" class="mt-2" onsubmit="return confirm(@json(__('church_mgmt.confirm_cancel')))">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('church_mgmt.cancel_booking') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
