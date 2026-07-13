@extends('layouts.app')

@section('title', __('events.title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="page-title mb-0">{{ __('events.title') }}</h1>
        <a href="{{ route('events.my-reservations') }}" class="btn btn-outline-theme btn-sm">{{ __('events.my_reservations') }}</a>
    </div>

@forelse($events as $event)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">{{ $event->title }}</h5>
                    <div class="text-muted small">{{ $event->starts_at->timezone(config('attendance.timezone'))->format('Y-m-d H:i') }}</div>
                    @if($event->location)<div class="small">{{ $event->location }}</div>@endif
                    <div class="small mt-1">{{ __('events.seats_remaining', ['count' => $event->seatsRemaining()]) }}</div>
                </div>
                <a href="{{ route('events.show', $event->event_id) }}" class="btn btn-primary btn-sm">{{ __('events.view') }}</a>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('events.none_visible') }}</p>
    @endforelse
</div>
@endsection
