@extends('layouts.app')

@section('title', __('nav.system_settings'))

@section('content')
<div class="container py-4 animate-in hub-page" style="max-width:920px;">
    <h1 class="page-title mb-2">{{ __('nav.system_settings') }}</h1>
    <p class="text-muted-theme mb-4">{{ __('nav.system_settings_desc') }}</p>

    <div class="row g-3">
        @foreach($links as $link)
            <div class="col-sm-6">
                <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ $link['active'] ? 'hub-tile-active' : '' }}">
                    <h3 class="h5 mb-2">
                        <i class="bi {{ $link['icon'] }}"></i>
                        {{ $link['label'] }}
                    </h3>
                    <span class="btn btn-sm btn-outline-theme mt-auto align-self-start">{{ __('nav.open') }}</span>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
