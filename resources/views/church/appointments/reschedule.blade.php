@extends('layouts.app')
@section('title', __('church_mgmt.reschedule'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.reschedule') }}</h1>
    <p class="text-muted-theme">
        {{ __('church_mgmt.current_slot') }}:
        {{ $booking->slot?->starts_at?->format('Y-m-d H:i') }}
        — {{ $booking->slot?->type?->displayName() }}
        — {{ $booking->slot?->priest?->displayName() }}
    </p>
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('church.appointments.bookings.reschedule.store', $booking) }}" class="app-card card shadow-sm">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label" for="appointment_slot_id">{{ __('church_mgmt.new_slot') }}</label>
                <select name="appointment_slot_id" id="appointment_slot_id" class="form-select" required>
                    <option value="">{{ __('church_mgmt.select_slot') }}</option>
                    @foreach($alternatives as $slot)
                        <option value="{{ $slot->appointment_slot_id }}">
                            {{ $slot->starts_at?->format('Y-m-d H:i') }} ({{ $slot->remainingCapacity() }}/{{ $slot->capacity }})
                            @if($slot->location) — {{ $slot->location }}@endif
                        </option>
                    @endforeach
                </select>
                @if($alternatives->isEmpty())
                    <div class="form-text text-danger">{{ __('church_mgmt.no_alt_slots') }}</div>
                @endif
            </div>
            <div>
                <label class="form-label" for="notes">{{ __('church_mgmt.notes') }}</label>
                <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes', $booking->notes) }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.appointments.my-bookings') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit" @disabled($alternatives->isEmpty())>{{ __('church_mgmt.reschedule') }}</button>
        </div>
    </form>
</div>
@endsection
