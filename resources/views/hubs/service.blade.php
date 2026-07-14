@extends('layouts.app')

@section('title', __('nav.service'))

@section('content')
<div class="container py-4 animate-in hub-page" style="max-width:920px;">
    <h1 class="page-title mb-2">{{ __('nav.service') }}</h1>
    <p class="text-muted-theme mb-2">{{ __('nav.service_desc') }}</p>

    @if(!empty($currentService))
        <p class="mb-4">
            <span class="badge bg-primary-subtle text-primary-emphasis border">
                <i class="fas fa-church me-1"></i>{{ $currentService->localizedTitle() }}
            </span>
            <span class="text-muted-theme small ms-2">{{ __('service.no_academic_hint') }}</span>
        </p>
    @else
        <p class="text-muted-theme mb-4">{{ __('service.select_hint') }}</p>
    @endif

    @if(empty($links))
        <div class="app-tile text-center text-muted-theme py-5">
            {{ __('service.no_services_hint') }}
        </div>
    @else
        @php
            $grouped = collect($links)->groupBy(fn ($link) => $link['category'] ?? 'ops');
            $sections = [
                'ops' => __('service.hub_section_ops'),
                'admin' => __('service.hub_section_admin'),
            ];
        @endphp

        @foreach($sections as $key => $title)
            @continue(($grouped[$key] ?? collect())->isEmpty())
            <h2 class="h6 text-muted-theme text-uppercase mb-2 {{ $loop->first ? 'mt-0' : 'mt-3' }}">{{ $title }}</h2>
            <div class="row g-3 mb-3">
                @foreach($grouped[$key] as $link)
                    <div class="col-sm-6">
                        <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ !empty($link['active']) ? 'hub-tile-active' : '' }}">
                            <h3 class="h5 mb-0">
                                @include('partials.icon', ['icon' => $link['icon']])
                                {{ $link['label'] }}
                            </h3>
                        </a>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</div>
@endsection
