@extends('layouts.app')

@section('title', $announcement->title)

@section('content')
<div class="container py-4 animate-in" style="max-width:760px;">
    <a href="{{ route('announcements.index') }}" class="text-decoration-none">&larr; {{ __('announcements.title') }}</a>

    <article class="app-card card shadow-sm mt-3">
        <div class="card-body">
            <h1 class="page-title h3 mb-2">{{ $announcement->title }}</h1>
            <p class="text-muted-theme small mb-4">
                {{ $announcement->published_at?->format('d/m/Y H:i') }}
                @if($announcement->course) · {{ $announcement->course->title }} @endif
            </p>
            <div class="announcement-body">{!! nl2br(e($announcement->body)) !!}</div>
        </div>
    </article>
</div>
@endsection
