@extends('layouts.app')

@section('title', __('notifications.hub_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('notifications.hub_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('notifications.hub_intro') }}</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('notifications.mark_all_read') }}</button>
            </form>
            <a href="{{ route('notifications.settings') }}" class="btn btn-primary btn-sm">{{ __('notifications.open_settings') }}</a>
        </div>
    </div>

<div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($filters as $filterKey)
            <a href="{{ route('notifications.index', ['filter' => $filterKey]) }}"
               class="btn btn-sm {{ $filter === $filterKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('notifications.filter_'.$filterKey) }}
            </a>
        @endforeach
    </div>

    <div class="app-card card shadow-sm">
        <div class="list-group list-group-flush">
            @forelse($notifications as $notification)
                <a href="{{ route('notifications.show', $notification) }}"
                   class="list-group-item list-group-item-action {{ $notification->isUnread() ? 'notification-unread' : '' }} {{ $notification->isAnnouncement() ? 'notification-announcement' : '' }}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ $notification->title }}</div>
                            <div class="small text-muted-theme">{{ $notification->body }}</div>
                        </div>
                        <div class="text-end small text-muted-theme text-nowrap">
                            @if($notification->isUnread())
                                <span class="badge bg-primary mb-1">{{ __('notifications.unread') }}</span>
                            @endif
                            <div>{{ $notification->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="list-group-item text-center text-muted-theme py-4">{{ __('notifications.no_notifications') }}</div>
            @endforelse
        </div>
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
</div>
@endsection
