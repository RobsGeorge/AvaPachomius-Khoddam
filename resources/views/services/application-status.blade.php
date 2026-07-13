@extends('layouts.app')

@section('title', __('service.application_status_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:640px;">
    <h1 class="page-title h4 mb-3">{{ __('service.application_status_title') }}</h1>
    <div class="app-card card shadow-sm">
        <div class="card-body">
            @if(! $application)
                <p class="mb-0 text-muted">{{ __('service.application_none') }}</p>
            @else
                <p class="mb-1"><strong>{{ __('pages.status') }}:</strong> {{ $application->status }}</p>
                @if($application->admin_note)
                    <p class="mb-0"><strong>{{ __('service.admin_note') }}:</strong> {{ $application->admin_note }}</p>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
