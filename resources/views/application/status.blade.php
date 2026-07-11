@extends('layouts.app')

@section('title', __('registration_review.waiting_title'))

@section('content')
@php
    use App\Models\RegistrationApplication;
    $status = $user->application_status ?? RegistrationApplication::STATUS_PENDING_REVIEW;
    $snapshot = $application?->snapshot ?? [];
@endphp
<div class="container py-4 animate-in">
    <div class="mb-4">
        @if($status === RegistrationApplication::STATUS_REJECTED)
            <h1 class="page-title mb-1">{{ __('registration_review.rejected_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('registration_review.rejected_intro') }}</p>
        @elseif($status === RegistrationApplication::STATUS_NEEDS_CORRECTION)
            <h1 class="page-title mb-1">{{ __('registration_review.correction_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('registration_review.correction_intro') }}</p>
        @else
            <h1 class="page-title mb-1">{{ __('registration_review.waiting_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('registration_review.waiting_intro') }}</p>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-4">
        <div class="card-body">
            <p class="mb-3">
                <span class="text-muted-theme">{{ __('registration_review.waiting_status_label') }}:</span>
                <span class="badge bg-secondary">{{ __('registration_review.status_'.$status) }}</span>
            </p>

            @if($status === RegistrationApplication::STATUS_REJECTED && filled($application?->overall_rejection_note))
                <div class="alert alert-danger">
                    <strong>{{ __('registration_review.overall_rejection_note') }}:</strong>
                    {{ $application->overall_rejection_note }}
                </div>
            @endif

            @if($status === RegistrationApplication::STATUS_NEEDS_CORRECTION)
                <a href="{{ route('application.edit') }}" class="btn btn-primary">
                    {{ __('registration_review.fix_application') }}
                </a>
            @endif
        </div>
    </div>

    @if($snapshot !== [])
        <div class="app-card card shadow-sm">
            <div class="card-header fw-semibold">{{ __('registration_review.review_title') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach(RegistrationApplication::REVIEWABLE_FIELDS as $field)
                        @continue($field === 'profile_photo' && empty($snapshot[$field]))
                        <div class="col-md-6">
                            <div class="small text-muted-theme">{{ __('registration_review.fields.'.$field) }}</div>
                            @if($field === 'profile_photo' && ! empty($snapshot[$field]))
                                <img src="{{ asset('storage/'.$snapshot[$field]) }}" alt="" class="rounded mt-1" style="max-height:120px;">
                            @else
                                <div class="fw-semibold">{{ $snapshot[$field] ?? '—' }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
