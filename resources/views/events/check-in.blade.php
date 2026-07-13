@extends('layouts.app')

@section('title', __('events.check_in_console'))

@section('content')
<div class="container py-4 animate-in" style="max-width:640px;">
    <h1 class="page-title mb-2">{{ __('events.check_in_console') }} — {{ $event->title }}</h1>
    <p class="text-muted small mb-4">{{ __('events.check_in_scan_hint') }}</p>
<form method="POST" action="{{ route('events.admin.check-in.record', $event->event_id) }}">@csrf
        <div class="mb-3"><label class="form-label">{{ __('pages.user_id') }}</label>
            <input type="number" name="user_id" class="form-control" required></div>
        <button type="submit" class="btn btn-primary">{{ __('events.record_check_in') }}</button>
    </form>
</div>
@endsection
