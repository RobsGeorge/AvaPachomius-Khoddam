@extends('layouts.app')

@section('title', __('announcements.whatsapp_dispatch'))

@section('content')
<div class="container py-4 animate-in student-data-hub">
    <div class="mb-4">
        <a href="{{ route('announcements.manage.edit', $announcement) }}" class="text-decoration-none">&larr; {{ __('announcements.edit') }}</a>
        <h1 class="page-title mt-2 mb-1">{{ __('announcements.whatsapp_dispatch') }}</h1>
        <p class="text-muted-theme">{{ __('announcements.whatsapp_help') }}</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-header fw-semibold">{{ __('announcements.copy_message') }}</div>
        <div class="card-body">
            <p class="fw-semibold mb-2">{{ $announcement->title }}</p>
            <p class="mb-0">{!! nl2br(e($announcement->body)) !!}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('announcements.manage.whatsapp.mark', $announcement) }}">
        @csrf
        @foreach($announcement->deliveries as $delivery)
            @php($user = $delivery->user)
            @continue(! $user)
            <article class="data-card mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="data-card-title mb-1">{{ $user->displayName() }}</div>
                        <small class="text-muted-theme">{{ $user->formattedMobile() ?? __('pages.not_available') }}</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        @if($url = $user->whatsappUrl($announcement->title."\n\n".$announcement->body))
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                                <i class="bi bi-whatsapp"></i> {{ __('students.whatsapp') }}
                            </a>
                        @endif
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="user_ids[]" value="{{ $user->user_id }}"
                                   id="wa_user_{{ $user->user_id }}"
                                   @checked($delivery->whatsapp_sent_at)>
                            <label class="form-check-label" for="wa_user_{{ $user->user_id }}">{{ __('announcements.mark_whatsapp_sent') }}</label>
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
        <button type="submit" class="btn btn-primary">{{ __('pages.save') }}</button>
    </form>
</div>
@endsection
