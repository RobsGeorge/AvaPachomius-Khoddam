@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
@php
    use App\Support\NavigationHub;
    $hasSystem = NavigationHub::hasSystem(Auth::user());
@endphp
<div class="animate-in" style="max-width: 920px; margin: 0 auto;">

    <h1 class="page-title">{{ __('dashboard.title') }}</h1>

    <div class="text-center my-5">
        <p class="display-5 fw-bold page-title mb-0">
            {{ __('dashboard.hello', ['name' => Auth::user()->first_name ?? __('dashboard.user_fallback')]) }}
        </p>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <a href="{{ route('hubs.academic') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-mortarboard"></i> {{ __('dashboard.academic_hub') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.academic_hub_desc') }}</p>
            </a>
        </div>

        @if($hasSystem)
            <div class="col-md-6">
                <a href="{{ route('hubs.system') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                    <h3><i class="bi bi-gear"></i> {{ __('dashboard.system_hub') }}</h3>
                    <p class="text-muted-theme mb-0">{{ __('dashboard.system_hub_desc') }}</p>
                </a>
            </div>
        @endif

        <div class="col-md-6">
            <a href="{{ route('profile') }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none">
                <h3><i class="bi bi-person-circle"></i> {{ __('dashboard.profile') }}</h3>
                <p class="text-muted-theme mb-0">{{ __('dashboard.profile_desc') }}</p>
            </a>
        </div>
    </div>
</div>
@endsection
