@extends('layouts.app')

@section('title', __('service.applications_admin_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <h1 class="page-title h4 mb-3">{{ __('service.applications_admin_title') }}</h1>
    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <p><strong>{{ __('pages.user') }}:</strong> {{ $application->user?->first_name }} {{ $application->user?->second_name }}</p>
            <p><strong>{{ __('service.label') }}:</strong> {{ $application->service?->localizedTitle() }}</p>
            <p><strong>{{ __('pages.status') }}:</strong> {{ $application->status }}</p>
            <p><strong>{{ __('service.application_message') }}:</strong> {{ $application->snapshot['message'] ?? '—' }}</p>
        </div>
    </div>

    @if($application->status === \App\Models\ServiceApplication::STATUS_PENDING)
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.service-applications.approve', $application) }}">
                @csrf
                <button class="btn btn-success" type="submit">{{ __('service.approve') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.service-applications.reject', $application) }}">
                @csrf
                <button class="btn btn-outline-danger" type="submit">{{ __('service.reject') }}</button>
            </form>
        </div>
    @endif
</div>
@endsection
