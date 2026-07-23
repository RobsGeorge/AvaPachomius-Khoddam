@extends('layouts.app')

@section('title', __('events.reservations'))

@section('content')
<div class="container py-4 animate-in">
    <h1 class="page-title mb-1">{{ $event->title }}</h1>
    <p class="text-muted mb-4">{{ __('events.reservations') }}</p>
<div class="app-card card shadow-sm mb-4">
        <div class="card-header">{{ __('events.add_exception') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('events.admin.exceptions.add', $event->event_id) }}" class="row g-2">@csrf
                <div class="col-md-5"><select name="user_id" class="form-select" required>
                    <option value="">{{ __('pages.select_option') }}</option>
                    @foreach($users as $u)<option value="{{ $u->user_id }}">{{ $u->first_name }} {{ $u->second_name }} ({{ $u->email }})</option>@endforeach
                </select></div>
                <div class="col-md-5"><input name="note" class="form-control" placeholder="{{ __('events.exception_note') }}"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('pages.add') }}</button></div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <h2 class="h6">{{ __('events.status_confirmed') }}</h2>
            <ul class="list-group">@forelse($event->reservations->where('status','confirmed') as $r)
                <li class="list-group-item">{{ $r->user?->first_name }} {{ $r->user?->email }}</li>@empty<li class="list-group-item text-muted">—</li>@endforelse</ul>
        </div>
        <div class="col-md-6">
            <h2 class="h6">{{ __('events.status_waitlist') }}</h2>
            <ul class="list-group">@forelse($event->reservations->where('status','waitlist') as $r)
                <li class="list-group-item">{{ $r->user?->first_name }} {{ $r->user?->email }}</li>@empty<li class="list-group-item text-muted">—</li>@endforelse</ul>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <a href="{{ route('events.admin.check-in', $event->event_id) }}" class="btn btn-outline-success btn-sm">{{ __('events.check_in_console') }}</a>
        <form method="POST" action="{{ route('events.admin.cancel', $event->event_id) }}" data-confirm="{{ __('events.confirm_cancel_event') }}">@csrf
            <button class="btn btn-outline-danger btn-sm">{{ __('events.cancel_event') }}</button>
        </form>
    </div>
</div>
@endsection
