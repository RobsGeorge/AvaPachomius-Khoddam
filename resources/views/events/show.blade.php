@extends('layouts.app')

@section('title', $event->title)

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <a href="{{ route('events.index') }}" class="text-muted small">{{ __('events.back') }}</a>
    <h1 class="page-title mt-2">{{ $event->title }}</h1>
    <p>{{ $event->description }}</p>
    <dl class="row small">
        @if($event->location)
            <dt class="col-sm-4">{{ __('events.location') }}</dt>
            <dd class="col-sm-8">{{ $event->location }}</dd>
        @endif
        <dt class="col-sm-4">{{ __('events.starts') }}</dt>
        <dd class="col-sm-8">{{ $event->starts_at->timezone(config('attendance.timezone'))->format('Y-m-d H:i') }}</dd>
        <dt class="col-sm-4">{{ __('events.capacity') }}</dt>
        <dd class="col-sm-8">{{ $event->seatsRemaining() }} / {{ $event->capacity }}</dd>
    </dl>

@if($reservation)
        <div class="alert alert-info">
            {{ __('events.your_status') }}: <strong>{{ __('events.status_'.$reservation->status) }}</strong>
        </div>
        @if($qrUrl)
            <div class="app-card card shadow-sm mb-3">
                <div class="card-body text-center">
                    <p class="fw-semibold mb-3">{{ __('events.check_in_qr') }}</p>
                    <div class="d-inline-block p-3 bg-white border rounded">
                        {!! QrCode::size(220)->margin(1)->generate($qrUrl) !!}
                    </div>
                    <p class="mt-3 mb-0 small text-muted-theme">{{ __('events.check_in_qr_hint') }}</p>
                </div>
            </div>
        @endif
        <form method="POST" action="{{ route('events.cancel', $event->event_id) }}">@csrf
            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(@json(__('events.confirm_cancel')))">{{ __('events.cancel_reservation') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('events.reserve', $event->event_id) }}">@csrf
            <button type="submit" class="btn btn-primary">{{ __('events.reserve') }}</button>
        </form>
    @endif
</div>
@endsection
