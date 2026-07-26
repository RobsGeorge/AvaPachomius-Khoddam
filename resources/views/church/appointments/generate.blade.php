@extends('layouts.app')
@section('title', __('church_mgmt.generate_weekly'))
@section('content')
<div class="container py-4" style="max-width:560px;">
    <h1 class="page-title mb-3">{{ __('church_mgmt.generate_weekly') }}</h1>
    <form method="POST" action="{{ route('church.appointments.generate.store') }}" class="app-card card shadow-sm">
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
                <div class="form-label">{{ __('church_mgmt.weekdays') }}</div>
                @foreach([1,2,3,4,5,6,7] as $n)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="weekdays[]" id="wd{{ $n }}" value="{{ $n }}" @checked(collect(old('weekdays', [6]))->contains($n))>
                        <label class="form-check-label" for="wd{{ $n }}">{{ __('church_mgmt.weekday_'.$n) }}</label>
                    </div>
                @endforeach
            </div>
            <div class="row g-2">
                <div class="col">
                    <label class="form-label" for="time_start">{{ __('church_mgmt.time_start') }}</label>
                    <input type="time" name="time_start" id="time_start" class="form-control" value="{{ old('time_start', '09:00') }}" required>
                </div>
                <div class="col">
                    <label class="form-label" for="time_end">{{ __('church_mgmt.time_end') }}</label>
                    <input type="time" name="time_end" id="time_end" class="form-control" value="{{ old('time_end', '10:00') }}" required>
                </div>
            </div>
            <div>
                <label class="form-label" for="weeks">{{ __('church_mgmt.weeks_ahead') }}</label>
                <input type="number" name="weeks" id="weeks" class="form-control" value="{{ old('weeks', 4) }}" min="1" max="26" required>
            </div>
            <div>
                <label class="form-label" for="capacity">{{ __('church_mgmt.capacity') }}</label>
                <input type="number" name="capacity" id="capacity" class="form-control" value="{{ old('capacity', $types->first()?->default_capacity ?? 1) }}" min="1" max="50">
            </div>
            <div>
                <label class="form-label" for="location">{{ __('church_mgmt.location') }}</label>
                <input type="text" name="location" id="location" class="form-control" value="{{ old('location') }}">
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('church.appointments.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.cancel') }}</a>
            <button class="btn btn-primary" type="submit">{{ __('church_mgmt.generate') }}</button>
        </div>
    </form>
</div>
@endsection
