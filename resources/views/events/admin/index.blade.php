@extends('layouts.app')

@section('title', __('events.admin_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-4 admin-page-header">
        <h1 class="page-title mb-0">{{ __('events.admin_title') }}</h1>
        <div class="admin-page-actions">
            <a href="{{ route('events.admin.create') }}" class="btn btn-primary btn-sm">{{ __('events.admin_create') }}</a>
        </div>
    </div>
<div class="table-responsive d-none d-lg-block admin-table-desktop app-card card shadow-sm">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ __('events.event') }}</th><th>{{ __('events.status') }}</th><th>{{ __('events.starts') }}</th><th>{{ __('events.capacity') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($events as $event)
                <tr>
                    <td>{{ $event->title }}</td>
                    <td>{{ $event->status }}</td>
                    <td>{{ $event->starts_at->timezone(config('attendance.timezone'))->format('Y-m-d H:i') }}</td>
                    <td>{{ $event->confirmedCount() }}/{{ $event->capacity }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('events.admin.reservations', $event->event_id) }}" class="btn btn-sm btn-outline-theme">{{ __('events.reservations') }}</a>
                        <a href="{{ route('events.admin.edit', $event->event_id) }}" class="btn btn-sm btn-outline-primary">{{ __('pages.edit') }}</a>
                        @if($event->status === 'draft')
                            <form method="POST" action="{{ route('events.admin.publish', $event->event_id) }}" class="d-inline">@csrf
                                <button class="btn btn-sm btn-success">{{ __('events.publish') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="d-lg-none admin-data-cards student-data-hub">
        @foreach($events as $event)
            <article class="data-card app-card card shadow-sm">
                <div class="card-body">
                    <div class="data-card-title">{{ $event->title }}</div>
                    <dl class="data-meta-list mb-3">
                        <div class="data-meta-row">
                            <dt>{{ __('events.status') }}</dt>
                            <dd>{{ $event->status }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('events.starts') }}</dt>
                            <dd>{{ $event->starts_at->timezone(config('attendance.timezone'))->format('Y-m-d H:i') }}</dd>
                        </div>
                        <div class="data-meta-row">
                            <dt>{{ __('events.capacity') }}</dt>
                            <dd>{{ $event->confirmedCount() }}/{{ $event->capacity }}</dd>
                        </div>
                    </dl>
                    <div class="data-card-actions d-flex flex-wrap gap-2">
                        <a href="{{ route('events.admin.reservations', $event->event_id) }}" class="btn btn-sm btn-outline-theme">{{ __('events.reservations') }}</a>
                        <a href="{{ route('events.admin.edit', $event->event_id) }}" class="btn btn-sm btn-outline-primary">{{ __('pages.edit') }}</a>
                        @if($event->status === 'draft')
                            <form method="POST" action="{{ route('events.admin.publish', $event->event_id) }}" class="w-100">@csrf
                                <button class="btn btn-sm btn-success w-100">{{ __('events.publish') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    {{ $events->links() }}
</div>
@endsection
