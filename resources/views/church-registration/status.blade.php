@extends('layouts.app')

@section('title', __('church_applications.status_page_title'))

@section('content')
<div class="container py-5 animate-in" style="max-width:640px;">
    <h1 class="page-title h3 mb-3">{{ __('church_applications.status_page_title') }}</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="card-body d-flex flex-column gap-2">
            <p class="mb-0">
                <strong>{{ __('church_applications.requested_name') }}:</strong>
                {{ $application->requested_name }}
            </p>
            <p class="mb-0">
                <strong>{{ __('church_applications.status') }}:</strong>
                {{ __('church_applications.status_'.$application->status) }}
            </p>
            <p class="mb-0">
                <strong>{{ __('church_applications.submitted_at') }}:</strong>
                {{ $application->submitted_at?->format('Y-m-d H:i') ?? '—' }}
            </p>
            @if($application->isUnverified())
                <p class="text-muted-theme mb-0">{{ __('church_applications.status_unverified_hint') }}</p>
            @endif
            @if($application->status === \App\Models\ChurchApplication::STATUS_PENDING)
                <p class="text-muted-theme mb-0">{{ __('church_applications.status_pending_hint') }}</p>
            @endif
            @if($application->status === \App\Models\ChurchApplication::STATUS_APPROVED)
                <p class="text-muted-theme mb-0">{{ __('church_applications.status_approved_hint') }}</p>
            @endif
            @if($application->status === \App\Models\ChurchApplication::STATUS_REJECTED)
                <p class="mb-0">
                    <strong>{{ __('church_applications.admin_note') }}:</strong>
                    {{ $application->admin_note ?: '—' }}
                </p>
            @endif
            @if($application->reviewed_at)
                <p class="mb-0 small text-muted-theme">
                    {{ __('church_applications.reviewed_at') }}:
                    {{ $application->reviewed_at->format('Y-m-d H:i') }}
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
