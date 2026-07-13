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

@forelse($items as $item)
        <article class="data-card mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <div>
                    <div class="data-card-title mb-1">{{ $item->title }}</div>
                    <small class="text-muted-theme">
                        {{ $item->isPublished() ? __('announcements.published_status') : __('announcements.draft') }}
                        @if($item->course) · {{ $item->course->title }} @endif
                    </small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('announcements.manage.edit', $item) }}" class="btn btn-sm btn-outline-primary">{{ __('announcements.edit') }}</a>
                    @if($item->isPublished())
                        <a href="{{ route('announcements.manage.directory', $item) }}" class="btn btn-sm btn-outline-secondary">{{ __('announcements.directory') }}</a>
                    @endif
                </div>
            </div>
            <p class="mb-0 text-muted-theme">{{ \Illuminate\Support\Str::limit($item->body, 180) }}</p>
        </article>
    @empty
        <div class="app-tile text-center text-muted-theme py-5">{{ __('announcements.no_announcements') }}</div>
    @endforelse

    {{ $items->links() }}
</div>
@endsection
