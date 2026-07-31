@extends('layouts.app')

@section('title', __('church_applications.admin_title'))

@section('content')
<div class="container py-4 animate-in" style="max-width:720px;">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
        <h1 class="page-title h4 mb-0">{{ $application->requested_name }}</h1>
        <a href="{{ route('superadmin.church-applications.index') }}" class="btn btn-sm btn-outline-secondary">
            {{ __('church_applications.back_to_list') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="app-card card shadow-sm mb-3">
        <div class="card-body">
            <p><strong>{{ __('church_applications.status') }}:</strong>
                {{ __('church_applications.status_'.$application->status) }}</p>
            @if($application->isUnverified())
                <div class="alert alert-warning py-2">{{ __('church_applications.email_not_verified_badge') }}</div>
            @endif
            <p><strong>{{ __('church_applications.requested_name') }}:</strong>
                {{ $application->requested_name }}</p>
            <p><strong>{{ __('church_applications.requested_short_name') }}:</strong>
                {{ $application->requested_short_name ?: '—' }}</p>
            <p><strong>{{ __('church_applications.place_country') }}:</strong>
                {{ $application->place_country_code ?: '—' }}</p>
            <p><strong>{{ __('church_applications.place_governorate') }}:</strong>
                {{ $application->place_governorate ?: '—' }}</p>
            <p><strong>{{ __('church_applications.place_district') }}:</strong>
                {{ $application->place_district ?: '—' }}</p>
            <p><strong>{{ __('church_applications.contact_name') }}:</strong>
                {{ $application->contact_name }}</p>
            <p><strong>{{ __('church_applications.contact_email') }}:</strong>
                {{ $application->contact_email }}</p>
            <p><strong>{{ __('church_applications.contact_mobile') }}:</strong>
                {{ $application->contact_mobile }}</p>
            <p><strong>{{ __('church_applications.message') }}:</strong>
                {{ $application->message ?: '—' }}</p>
            <p><strong>{{ __('church_applications.submitted_at') }}:</strong>
                {{ $application->submitted_at?->format('Y-m-d H:i') ?? '—' }}</p>
            @if($application->reviewed_at)
                <p><strong>{{ __('church_applications.reviewed_at') }}:</strong>
                    {{ $application->reviewed_at->format('Y-m-d H:i') }}</p>
                <p><strong>{{ __('church_applications.reviewed_by') }}:</strong>
                    {{ $application->reviewer?->email ?? '—' }}</p>
            @endif
            @if(filled($application->admin_note))
                <p><strong>{{ __('church_applications.admin_note') }}:</strong>
                    {{ $application->admin_note }}</p>
            @endif
        </div>
    </div>

    @if($application->isUnverified())
        <p class="text-muted-theme">{{ __('church_applications.approve_disabled_until_verified') }}</p>
    @elseif($application->status === \App\Models\ChurchApplication::STATUS_PENDING)
        <div class="d-flex flex-column gap-3">
            <form method="POST" action="{{ route('superadmin.church-applications.approve', $application) }}">
                @csrf
                <button class="btn btn-success" type="submit">{{ __('church_applications.approve') }}</button>
            </form>

            <form method="POST" action="{{ route('superadmin.church-applications.reject', $application) }}"
                  class="app-card card shadow-sm">
                @csrf
                <div class="card-body">
                    <label class="form-label" for="admin_note">{{ __('church_applications.admin_note_required') }}</label>
                    <textarea id="admin_note" name="admin_note" rows="3" required
                              class="form-control @error('admin_note') is-invalid @enderror"
                              maxlength="2000">{{ old('admin_note') }}</textarea>
                    @error('admin_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-outline-danger mt-3" type="submit">{{ __('church_applications.reject') }}</button>
                </div>
            </form>
        </div>
    @endif

    @if($application->status === \App\Models\ChurchApplication::STATUS_APPROVED)
        <div class="alert alert-info">
            <p class="mb-2">{{ __('church_applications.create_church_hint') }}</p>
            <a class="btn btn-primary"
               href="{{ route('superadmin.churches.create', array_filter([
                   'name' => $application->requested_name,
                   'short_name' => $application->requested_short_name,
                   'place_district' => $application->place_district,
                   'place_governorate' => $application->place_governorate,
                   'place_country_code' => $application->place_country_code,
               ])) }}">
                {{ __('church_applications.create_church') }}
            </a>
        </div>
    @endif
</div>
@endsection
