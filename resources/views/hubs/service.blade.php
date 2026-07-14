@extends('layouts.app')

@section('title', __('nav.service'))

@section('content')
<div class="container py-4 animate-in hub-page" style="max-width:920px;">
    <h1 class="page-title mb-2">{{ __('nav.service') }}</h1>
    <p class="text-muted-theme mb-2">{{ __('nav.service_desc') }}</p>

    @if(!empty($currentService))
        <p class="mb-4">
            <span class="badge bg-primary-subtle text-primary-emphasis border">
                <i class="bi bi-building me-1"></i>{{ $currentService->localizedTitle() }}
            </span>
            <span class="text-muted-theme small ms-2">{{ __('service.no_academic_hint') }}</span>
        </p>
    @else
        <p class="text-muted-theme mb-4">{{ __('service.select_hint') }}</p>
    @endif

    <div class="row g-3">
        @forelse($links as $link)
            <div class="col-sm-6">
                <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ !empty($link['active']) ? 'hub-tile-active' : '' }}">
                    <h3 class="h5 mb-0">
                        <i class="bi {{ $link['icon'] }}"></i>
                        {{ $link['label'] }}
                    </h3>
                </a>
            </div>
        @empty
            <div class="col-12">
                <div class="app-tile text-center text-muted-theme py-5">
                    {{ __('service.no_services_hint') }}
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
