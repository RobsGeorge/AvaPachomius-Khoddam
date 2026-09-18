@extends('layouts.app')

@section('title', __('announcements.manage_title'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('announcements.manage_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('announcements.manage_intro') }}</p>
        </div>
        <a href="{{ route('announcements.manage.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('announcements.create') }}
        </a>
    </div>

    @if($openItems->isEmpty() && $finishedItems->isEmpty())
        <div class="app-tile text-center text-muted-theme py-5">{{ __('announcements.no_announcements') }}</div>
    @else
        <h2 class="h5 text-muted-theme mb-3">{{ __('announcements.section_open') }}</h2>
        @forelse($openItems as $item)
            @include('announcements.manage.partials.list-card', ['item' => $item])
        @empty
            <div class="app-tile text-center text-muted-theme py-4 mb-4">{{ __('announcements.no_open') }}</div>
        @endforelse

        @if($finishedItems->isNotEmpty())
            <h2 class="h5 text-muted-theme mb-3 mt-4">{{ __('announcements.section_finished') }}</h2>
            @foreach($finishedItems as $item)
                @include('announcements.manage.partials.list-card', ['item' => $item])
            @endforeach
        @endif
    @endif
</div>
@endsection
