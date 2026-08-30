@extends('layouts.app')

@section('title', __('nav.service'))

@section('content')
@php
    use App\Support\NavigationHub;
    $sections = NavigationHub::groupedSections($links, NavigationHub::serviceSectionDefinitions());
@endphp
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

    @if(empty($sections))
        <div class="app-tile text-center text-muted-theme py-5">
            {{ __('service.no_services_hint') }}
        </div>
    @else
        @include('partials.hub-link-sections', ['sections' => $sections])
    @endif
</div>
@endsection
