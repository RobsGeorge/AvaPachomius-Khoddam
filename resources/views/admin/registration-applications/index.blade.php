@extends('layouts.app')

@section('title', __('registration_review.queue_title'))

@section('content')
@php
    use App\Models\RegistrationApplication;
@endphp
<div class="container-fluid py-4 animate-in student-data-hub">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="page-title mb-1">{{ __('registration_review.queue_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('registration_review.queue_intro') }}</p>
        </div>
        <a href="{{ route('admin.registration-applications.templates') }}" class="btn btn-outline-secondary btn-sm">
            {{ __('registration_review.manage_templates') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3 admin-filter-bar">
        <a href="{{ route('admin.registration-applications.index') }}"
           class="btn btn-sm {{ $filter ? 'btn-outline-secondary' : 'btn-primary' }}">
            {{ __('registration_review.filter_all') }} ({{ array_sum($counts) }})
        </a>
        @foreach(RegistrationApplication::statuses() as $statusKey)
            <a href="{{ route('admin.registration-applications.index', ['filter' => $statusKey]) }}"
               class="btn btn-sm {{ $filter === $statusKey ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __('registration_review.status_'.$statusKey) }} ({{ $counts[$statusKey] ?? 0 }})
            </a>
        @endforeach
    </div>

    <div class="table-responsive app-card card shadow-sm">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('registration_review.applicant') }}</th>
                    <th>{{ __('registration_review.submitted_at') }}</th>
                    <th>{{ __('registration_review.version') }}</th>
                    <th>{{ __('registration_review.waiting_status_label') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $application->user?->displayName() }}</div>
                            <div class="small text-muted-theme">{{ $application->user?->email }}</div>
                        </td>
                        <td>{{ $application->submitted_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $application->version }}</td>
                        <td>
                            <span class="badge bg-secondary">{{ __('registration_review.status_'.$application->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.registration-applications.show', $application) }}" class="btn btn-sm btn-primary">
                                {{ __('registration_review.review') }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted-theme py-4">{{ __('registration_review.no_applications') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
