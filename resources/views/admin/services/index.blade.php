@extends('layouts.app')

@section('title', __('service.manage_title'))

@section('content')
<div class="container py-4 animate-in">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        @if(auth()->user()->is_superadmin ?? false)
            <a href="{{ route('superadmin.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> {{ __('pages.back_to_superadmin') }}
            </a>
        @else
            <a href="{{ route('hubs.service') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> {{ __('nav.service') }}
            </a>
        @endif
        <h1 class="page-title mb-0">
            <i class="fas fa-church me-2"></i>{{ __('service.manage_title') }}
        </h1>
    </div>

    <p class="text-muted-theme small mb-4">
        {{ $requiresChurch ? __('service.manage_intro_console') : __('service.manage_intro') }}
    </p>

    @include('admin.services.partials.panel')
</div>
@endsection
