@extends('layouts.app')

@section('title', __('announcements.edit'))

@section('content')
<div class="container py-4 animate-in" style="max-width:920px;">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <h1 class="page-title mb-0">{{ __('announcements.edit') }}</h1>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('announcements.manage.publish', $announcement) }}"
                  onsubmit="return confirm(@json(__('announcements.publish').'?'))">
                @csrf
                <button type="submit" class="btn btn-success">
                    {{ $announcement->isPublished() ? __('announcements.republish') : __('announcements.publish') }}
                </button>
            </form>
            @if($announcement->isPublished() && $announcement->hasChannel(\App\Models\Announcement::CHANNEL_EMAIL))
                <form method="POST" action="{{ route('announcements.manage.resend-email', $announcement) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">{{ __('announcements.resend_email') }}</button>
                </form>
            @endif
            @if($announcement->isPublished() && $announcement->hasChannel(\App\Models\Announcement::CHANNEL_WHATSAPP))
                <a href="{{ route('announcements.manage.whatsapp', $announcement) }}" class="btn btn-outline-success">
                    {{ __('announcements.whatsapp_dispatch') }}
                </a>
            @endif
            <a href="{{ route('announcements.manage.directory', $announcement) }}" class="btn btn-outline-secondary">
                {{ __('announcements.directory') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @include('announcements.manage.partials.form', [
        'announcement' => $announcement,
        'action' => route('announcements.manage.update', $announcement),
        'method' => 'PUT',
        'courses' => $courses,
        'students' => $students,
        'selectedCourse' => $announcement->course_id,
    ])

    @if($announcement->revisions->isNotEmpty())
        <div class="app-card card shadow-sm mt-4">
            <div class="card-header fw-semibold">{{ __('announcements.revision_history') }}</div>
            <div class="card-body">
                @foreach($announcement->revisions->sortByDesc('created_at') as $revision)
                    <div class="mb-2 pb-2 border-bottom">
                        <strong>{{ __('announcements.action_'.$revision->action) }}</strong>
                        · {{ __('announcements.by', ['name' => $revision->editor?->displayName() ?? '—']) }}
                        · {{ __('announcements.on', ['date' => $revision->created_at?->format('d/m/Y H:i') ?? '—']) }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
