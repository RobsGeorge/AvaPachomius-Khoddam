@extends('layouts.app')

@section('title', __('nav.academic'))

@section('content')
<div class="container py-4 animate-in hub-page" style="max-width:920px;">
    <h1 class="page-title mb-2">{{ __('nav.academic') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('nav.academic_desc') }}</p>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <a href="{{ route('notifications.index') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none position-relative {{ !empty($unreadNotificationBadge) ? 'hub-tile-highlight' : '' }}">
                <h3 class="h5 mb-0">
                    <i class="bi bi-bell"></i>
                    {{ __('notifications.dashboard_tile') }}
                </h3>
                @if(!empty($unreadNotificationBadge))
                    <span class="badge bg-danger mt-2 align-self-start">{{ $unreadNotificationBadge }}</span>
                @endif
            </a>
        </div>
    </div>

    <div class="row g-3">
        @foreach($links as $link)
            <div class="col-sm-6">
                <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ $link['active'] ? 'hub-tile-active' : '' }}">
                    <h3 class="h5 mb-0">
                        <i class="bi {{ $link['icon'] }}"></i>
                        {{ $link['label'] }}
                    </h3>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
