@extends('layouts.app')

@section('title', __('events.my_reservations'))

@section('content')
<div class="container py-4 animate-in">
    <h1 class="page-title mb-4">{{ __('events.my_reservations') }}</h1>
    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ __('events.event') }}</th><th>{{ __('events.status') }}</th><th>{{ __('events.reserved_at') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($reservations as $r)
                <tr>
                    <td>{{ $r->event?->title }}</td>
                    <td>{{ __('events.status_'.$r->status) }}</td>
                    <td>{{ $r->reserved_at?->timezone(config('attendance.timezone'))->format('Y-m-d H:i') }}</td>
                    <td>@if($r->isActive() && $r->event)<a href="{{ route('events.show', $r->event_id) }}" class="btn btn-sm btn-outline-theme">{{ __('events.view') }}</a>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $reservations->links() }}
</div>
@endsection
