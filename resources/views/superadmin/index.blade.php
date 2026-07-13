@extends('layouts.app')

@section('title', __('pages.superadmin_title'))

@section('content')
<div class="container py-4 animate-in hub-page" style="max-width:920px;">
    <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
        <span class="badge bg-danger fs-6 px-3 py-2">
            <i class="bi bi-shield-lock-fill"></i> {{ __('pages.superadmin_role') }}
        </span>
        <h1 class="page-title mb-0">{{ __('pages.superadmin_title') }}</h1>
    </div>
    <p class="text-muted-theme mb-4">{{ __('pages.superadmin_hub_desc') }}</p>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(!empty($isSystemWideMode))
        <div class="app-card card shadow-sm mb-4 border-danger border-opacity-25">
            <div class="card-body">
                <h2 class="h6 mb-2">
                    <i class="bi bi-globe2"></i> {{ __('course_context.system_wide_mode') }}
                </h2>
                <p class="text-muted-theme small mb-3">{{ __('pages.superadmin_system_wide_hint') }}</p>
                <a href="{{ route('courses.select') }}" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-grid"></i> {{ __('course_context.switch_course') }}
                </a>
            </div>
        </div>
    @elseif(!empty($currentCourse))
        <div class="app-card card shadow-sm mb-4 border-primary border-opacity-25">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="h6 mb-1">
                        <i class="bi bi-mortarboard"></i> {{ __('course_context.current_course') }}
                    </h2>
                    <p class="mb-0 fw-semibold">{{ $currentCourse->localizedTitle() }}</p>
                    <p class="text-muted-theme small mb-0">{{ __('pages.superadmin_course_context_hint') }}</p>
                </div>
                <form method="POST" action="{{ route('courses.select.clear') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-globe2"></i> {{ __('course_context.system_wide_mode') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    @foreach($sections as $section)
        <h2 class="h6 text-muted-theme text-uppercase mb-3 mt-4">{{ $section['title'] }}</h2>
        <div class="row g-3 mb-2">
            @foreach($section['links'] as $link)
                <div class="col-sm-6">
                    <a href="{{ $link['url'] }}" class="app-tile hub-tile d-flex flex-column h-100 text-decoration-none {{ $link['active'] ? 'hub-tile-active' : '' }}">
                        <h3 class="h5 mb-1">
                            <i class="bi {{ $link['icon'] }}"></i>
                            {{ $link['label'] }}
                        </h3>
                        @if(!empty($link['description']))
                            <p class="text-muted-theme small mb-0">{{ $link['description'] }}</p>
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
