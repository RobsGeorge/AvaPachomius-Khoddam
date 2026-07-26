@extends('layouts.app')
@section('title', __('church_mgmt.my_bookings'))
@section('content')
<div class="container py-4" style="max-width:900px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="page-title mb-0">{{ __('church_mgmt.my_bookings') }}</h1>
        <a href="{{ route('church.appointments.index') }}" class="btn btn-outline-secondary">{{ __('church_mgmt.back') }}</a>
    </div>
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('church_mgmt.starts_at') }}</th>
                        <th>{{ __('church_mgmt.appointment_type') }}</th>
                        <th>{{ __('church_mgmt.priests_title') }}</th>
                        <th>{{ __('church_mgmt.priest_status') }}</th>
                        <th>{{ __('church_mgmt.notes') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td>{{ $booking->slot?->starts_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td>{{ $booking->slot?->type?->displayName() }}</td>
                            <td>{{ $booking->slot?->priest?->displayName() }}</td>
                            <td>{{ __('church_mgmt.status_'.$booking->status) }}</td>
                            <td>
                                @if($booking->status === 'confirmed')
                                    <form method="POST" action="{{ route('church.appointments.bookings.notes', $booking) }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="text" name="notes" class="form-control form-control-sm" value="{{ $booking->notes }}" maxlength="2000">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">{{ __('church_mgmt.save') }}</button>
                                    </form>
                                @else
                                    {{ $booking->notes ?: '—' }}
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($booking->status === 'confirmed')
                                    <a href="{{ route('church.appointments.bookings.reschedule', $booking) }}" class="btn btn-sm btn-outline-primary">{{ __('church_mgmt.reschedule') }}</a>
                                    <form method="POST" action="{{ route('church.appointments.bookings.cancel', $booking) }}" class="d-inline" onsubmit="return confirm(@json(__('church_mgmt.confirm_cancel')))">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('church_mgmt.cancel_booking') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-theme py-4">{{ __('church_mgmt.no_bookings') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $bookings->links() }}</div>
</div>
@endsection
