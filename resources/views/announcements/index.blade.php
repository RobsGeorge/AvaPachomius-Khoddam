@extends('layouts.app')

@section('title', __('announcements.title'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <h1 class="page-title mb-2">{{ __('announcements.title') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('announcements.student_intro') }}</p>

    @forelse($deliveries as $delivery)
        @php($announcement = $delivery->announcement)
        <a href="{{ route('announcements.show', $announcement) }}"
           class="app-tile hub-tile d-block text-decoration-none mb-3 {{ $delivery->isUnread() ? 'announcement-card-unread' : '' }}">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h3 class="h5 mb-1">{{ $announcement->title }}</h3>
                    <p class="text-muted-theme mb-2">{{ \Illuminate\Support\Str::limit($announcement->body, 140) }}</p>
                    <small class="text-muted-theme">
                        {{ $announcement->published_at?->format('d/m/Y H:i') }}
                        @if($delivery->isUnread())
                            · <span class="badge bg-primary">{{ __('announcements.unread') }}</span>
                        @endif
                    </small>
                </div>
                <i class="bi bi-chevron-left"></i>
            </div>
        </a>
    @empty
        <div class="app-tile text-center text-muted-theme py-5">{{ __('announcements.no_announcements') }}</div>
    @endforelse
</div>
@endsection
