@extends('layouts.app')

@section('title', __('projects.change_requests'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <h1 class="page-title mb-0">{{ __('projects.change_requests') }}</h1>
        <a href="{{ route('projects.manage') }}" class="btn btn-outline-secondary">{{ __('projects.manage') }}</a>
    </div>

    @forelse($requests as $changeRequest)
        <div class="app-card card shadow-sm mb-3">
            <div class="card-body">
                <div class="mb-2">
                    <div class="fw-bold">{{ $changeRequest->user?->displayName() }}</div>
                    <div class="small text-muted">
                        {{ $changeRequest->user?->mobile_number ?: __('projects.phone_missing') }}
                        · {{ $changeRequest->assessment?->title }}
                        · {{ __('projects.from_team') }}: {{ $changeRequest->fromProject?->title }}
                    </div>
                </div>
                <p style="white-space: pre-wrap;">{{ $changeRequest->reason }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('projects.change-requests.approve', $changeRequest) }}">
                        @csrf
                        <button class="btn btn-sm btn-success">{{ __('projects.change_approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('projects.change-requests.reject', $changeRequest) }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="admin_notes" class="form-control form-control-sm" placeholder="{{ __('projects.admin_notes') }}">
                        <button class="btn btn-sm btn-outline-danger">{{ __('projects.change_reject') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted">{{ __('projects.no_change_requests') }}</p>
    @endforelse
</div>
@endsection
